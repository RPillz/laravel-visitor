<?php

namespace RPillz\LaravelVisitor\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use RPillz\LaravelVisitor\Jobs\TrackVisitJob;
use RPillz\LaravelVisitor\LaravelVisitor;
use RPillz\LaravelVisitor\Models\VisitorIgnore;
use RPillz\LaravelVisitor\Support\AgentResolver;
use RPillz\LaravelVisitor\Support\HeaderFingerprint;
use RPillz\LaravelVisitor\Support\VerifiedCrawlerResolver;
use Symfony\Component\HttpFoundation\Response;

class TrackVisit
{
    public function __construct(
        protected LaravelVisitor $visitor,
        protected AgentResolver $agentResolver,
        protected HeaderFingerprint $headerFingerprint,
        protected VerifiedCrawlerResolver $verifiedCrawlerResolver,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->isBlocked($request)) {
            return $this->blockResponse($request);
        }

        if (config('visitor.block_probes', true)) {
            if ($this->isProbe($request) && ! $this->verifiedCrawlerResolver->isVerified($request)) {
                $this->autoBlock($request);

                return $this->blockResponse($request, 404);
            }

            if ($this->hasExceeded404RateLimit($request)) {
                return $this->blockResponse($request, 429);
            }
        }

        if ($this->hasExceededFingerprintRateLimit($request)) {
            return $this->blockResponse($request, 429);
        }

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        $isVerified = $this->verifiedCrawlerResolver->isVerified($request);

        if ($this->shouldTrack($request, $response)) {
            ($isVerified ? $this->visitor->verified() : $this->visitor)->track($request);
        }

        if (! $isVerified) {
            $this->maybeBlockFor404s($request, $response);
            $this->trackFingerprintRate($request, $response);
        }
    }

    protected function shouldTrack(Request $request, Response $response): bool
    {
        if (! in_array(strtoupper($request->method()), config('visitor.track_methods', ['GET']))) {
            return false;
        }

        if (! $response->isSuccessful() && ! $response->isRedirection()) {
            return false;
        }

        if (in_array($request->ip(), config('visitor.exclude_ips', []))) {
            return false;
        }

        foreach (config('visitor.exclude_paths', []) as $pattern) {
            if (Str::is($pattern, $request->path())) {
                return false;
            }
        }

        if (! config('visitor.track_bots', true) && $request->userAgent()
            && $this->agentResolver->isBot($request->userAgent())) {
            return false;
        }

        if ($this->isIgnored($request)) {
            return false;
        }

        return true;
    }

    protected function blockResponse(Request $request, int $status = 403): Response
    {
        if (config('visitor.log_blocks', false)) {
            $this->trackBlocked($request);
        }

        $messages = [403 => 'Forbidden', 404 => 'Not Found', 429 => 'Too Many Requests'];

        return response($messages[$status] ?? '', $status);
    }

    protected function hasExceeded404RateLimit(Request $request): bool
    {
        if (! $request->ip()) {
            return false;
        }

        $config = config('visitor.probe_404', []);
        $threshold = (int) ($config['threshold'] ?? 10);
        $key = 'visitor_404_'.LaravelVisitor::resolveConnection().'_'.$request->ip();

        return RateLimiter::tooManyAttempts($key, $threshold);
    }

    protected function hasExceededFingerprintRateLimit(Request $request): bool
    {
        if (! config('visitor.rate_limit.enabled', false)) {
            return false;
        }

        $threshold = (int) config('visitor.rate_limit.threshold', 60);
        $key = 'visitor_rl_'.LaravelVisitor::resolveConnection().'_'.$this->headerFingerprint->compute($request);

        return RateLimiter::tooManyAttempts($key, $threshold);
    }

    protected function trackFingerprintRate(Request $request, Response $response): void
    {
        if (! config('visitor.rate_limit.enabled', false)) {
            return;
        }

        // Do not count responses that are themselves rate-limit rejections.
        if ($response->getStatusCode() === 429) {
            return;
        }

        $fingerprint = $this->headerFingerprint->compute($request);
        $threshold = (int) config('visitor.rate_limit.threshold', 60);
        $window = (int) config('visitor.rate_limit.window', 1);
        $key = 'visitor_rl_'.LaravelVisitor::resolveConnection().'_'.$fingerprint;

        RateLimiter::hit($key, $window * 60);

        if (config('visitor.rate_limit.auto_block', true) && RateLimiter::tooManyAttempts($key, $threshold)) {
            $duration = config('visitor.probe_block_duration');
            $expiresAt = $duration ? now()->addMinutes((int) $duration) : null;

            VisitorIgnore::updateOrCreate(
                ['type' => 'header_fingerprint', 'value' => $fingerprint],
                ['is_blocked' => true, 'is_automatic' => true, 'expires_at' => $expiresAt],
            );
        }
    }

    protected function trackBlocked(Request $request): void
    {
        $connection = LaravelVisitor::resolveConnection();
        $path = '/'.ltrim($request->path(), '/');
        $anonymous = config('visitor.anonymous', false);

        TrackVisitJob::dispatch(
            dbConnection: $connection,
            url: $request->fullUrl(),
            path: $path,
            query: $request->getQueryString(),
            referrer: $request->header('referer'),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
            sessionId: $request->hasSession() ? $request->session()->getId() : null,
            isUser: auth()->check(),
            userId: (! $anonymous && auth()->check()) ? auth()->id() : null,
            isBlocked: true,
            headerFingerprint: $this->headerFingerprint->compute($request),
            looksLikeBrowser: $this->headerFingerprint->looksLikeBrowser($request),
        )
            ->onConnection(config('visitor.queue.connection'))
            ->onQueue(config('visitor.queue.name', 'default'));
    }

    protected function isProbe(Request $request): bool
    {
        $paths = config('visitor.probe_paths', []);

        if (empty($paths)) {
            return false;
        }

        foreach ($paths as $pattern) {
            if (Str::is($pattern, $request->path())) {
                return true;
            }
        }

        return false;
    }

    protected function autoBlock(Request $request): void
    {
        $duration = config('visitor.probe_block_duration');
        $expiresAt = $duration ? now()->addMinutes((int) $duration) : null;

        if ($request->ip()) {
            VisitorIgnore::updateOrCreate(
                ['type' => 'ip', 'value' => $request->ip()],
                ['is_blocked' => true, 'is_automatic' => true, 'expires_at' => $expiresAt],
            );
        }

        $fingerprint = $this->headerFingerprint->compute($request);
        VisitorIgnore::updateOrCreate(
            ['type' => 'header_fingerprint', 'value' => $fingerprint],
            ['is_blocked' => true, 'is_automatic' => true, 'expires_at' => $expiresAt],
        );
    }

    protected function maybeBlockFor404s(Request $request, Response $response): void
    {
        if ($response->getStatusCode() !== 404) {
            return;
        }

        if (! $request->ip()) {
            return;
        }

        if ($this->isIgnored($request)) {
            return;
        }

        $config = config('visitor.probe_404', []);

        if (! config('visitor.block_probes', true)) {
            return;
        }

        $threshold = (int) ($config['threshold'] ?? 10);
        $window = (int) ($config['window'] ?? 5);

        $key = 'visitor_404_'.LaravelVisitor::resolveConnection().'_'.$request->ip();

        RateLimiter::hit($key, $window * 60);

        if (RateLimiter::tooManyAttempts($key, $threshold)) {
            $this->autoBlock($request);
        }
    }

    protected function isBlocked(Request $request): bool
    {
        $list = $this->getIgnoreList();

        if ($request->ip()) {
            foreach ($list['ip'] ?? [] as $entry) {
                if ($entry['is_blocked'] && $entry['value'] === $request->ip() && $this->isActive($entry)) {
                    return true;
                }
            }
        }

        if (auth()->check()) {
            foreach ($list['user_id'] ?? [] as $entry) {
                if ($entry['is_blocked'] && $entry['value'] === (string) auth()->id() && $this->isActive($entry)) {
                    return true;
                }
            }
        }

        if ($request->userAgent()) {
            foreach ($list['user_agent'] ?? [] as $entry) {
                if ($entry['is_blocked'] && Str::is($entry['value'], $request->userAgent()) && $this->isActive($entry)) {
                    return true;
                }
            }
        }

        $fingerprint = $this->headerFingerprint->compute($request);
        foreach ($list['header_fingerprint'] ?? [] as $entry) {
            if ($entry['is_blocked'] && $entry['value'] === $fingerprint && $this->isActive($entry)) {
                return true;
            }
        }

        // Bot name and unverified bot checks — only run when the relevant config is active.
        $blockNames = config('visitor.block_verified_bots', []);
        $blockUnverified = config('visitor.block_unverified_bots', false);

        if (($blockNames || $blockUnverified) && $request->userAgent()) {
            $botName = $this->agentResolver->botName($request->userAgent());

            if ($botName && in_array($botName, $blockNames, true)) {
                return true;
            }

            if ($botName && $blockUnverified && ! $this->verifiedCrawlerResolver->isVerified($request)) {
                return true;
            }
        }

        return false;
    }

    protected function isIgnored(Request $request): bool
    {
        $list = $this->getIgnoreList();

        if ($request->ip()) {
            $active = collect($list['ip'] ?? [])->filter(fn ($e) => $this->isActive($e));
            if ($active->pluck('value')->contains($request->ip())) {
                return true;
            }
        }

        if (auth()->check()) {
            $active = collect($list['user_id'] ?? [])->filter(fn ($e) => $this->isActive($e));
            if ($active->pluck('value')->contains((string) auth()->id())) {
                return true;
            }
        }

        if ($request->userAgent()) {
            foreach ($list['user_agent'] ?? [] as $entry) {
                if ($this->isActive($entry) && Str::is($entry['value'], $request->userAgent())) {
                    return true;
                }
            }
        }

        return false;
    }

    protected function isActive(array $entry): bool
    {
        return ! $entry['expires_at'] || $entry['expires_at']->isFuture();
    }

    protected function getIgnoreList(): array
    {
        return Cache::remember('visitor.ignore_list.'.LaravelVisitor::resolveConnection(), now()->addMinutes(5), function () {
            return VisitorIgnore::where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })->get()
                ->groupBy('type')
                ->map(fn ($items) => $items->map(fn ($item) => [
                    'value' => $item->value,
                    'is_blocked' => (bool) $item->is_blocked,
                    'expires_at' => $item->expires_at,
                ])->values()->all())
                ->all();
        });
    }
}
