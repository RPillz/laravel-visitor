<?php

namespace RPillz\LaravelVisitor\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RPillz\LaravelVisitor\LaravelVisitor;
use RPillz\LaravelVisitor\Models\VisitorIgnore;
use RPillz\LaravelVisitor\Support\AgentResolver;
use Symfony\Component\HttpFoundation\Response;

class TrackVisit
{
    public function __construct(
        protected LaravelVisitor $visitor,
        protected AgentResolver $agentResolver,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->isBlocked($request)) {
            return response('Forbidden', 403);
        }

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if ($this->shouldTrack($request, $response)) {
            $this->visitor->track($request);
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

    protected function isBlocked(Request $request): bool
    {
        $list = $this->getIgnoreList();

        if ($request->ip()) {
            foreach ($list['ip'] ?? [] as $entry) {
                if ($entry['is_blocked'] && $entry['value'] === $request->ip()) {
                    return true;
                }
            }
        }

        if (auth()->check()) {
            foreach ($list['user_id'] ?? [] as $entry) {
                if ($entry['is_blocked'] && $entry['value'] === (string) auth()->id()) {
                    return true;
                }
            }
        }

        if ($request->userAgent()) {
            foreach ($list['user_agent'] ?? [] as $entry) {
                if ($entry['is_blocked'] && Str::is($entry['value'], $request->userAgent())) {
                    return true;
                }
            }
        }

        return false;
    }

    protected function isIgnored(Request $request): bool
    {
        $list = $this->getIgnoreList();

        if ($request->ip() && collect($list['ip'] ?? [])->pluck('value')->contains($request->ip())) {
            return true;
        }

        if (auth()->check() && collect($list['user_id'] ?? [])->pluck('value')->contains((string) auth()->id())) {
            return true;
        }

        if ($request->userAgent()) {
            foreach ($list['user_agent'] ?? [] as $entry) {
                if (Str::is($entry['value'], $request->userAgent())) {
                    return true;
                }
            }
        }

        return false;
    }

    protected function getIgnoreList(): array
    {
        return Cache::remember('visitor.ignore_list.'.LaravelVisitor::resolveConnection(), now()->addMinutes(5), function () {
            return VisitorIgnore::all()
                ->groupBy('type')
                ->map(fn ($items) => $items->map(fn ($item) => [
                    'value' => $item->value,
                    'is_blocked' => (bool) $item->is_blocked,
                ])->values()->all())
                ->all();
        });
    }
}
