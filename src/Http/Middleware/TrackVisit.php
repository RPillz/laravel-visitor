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

        if (config('visitor.exclude_bots', true) && $request->userAgent()
            && $this->agentResolver->isBot($request->userAgent())) {
            return false;
        }

        if ($this->isIgnored($request)) {
            return false;
        }

        return true;
    }

    protected function isIgnored(Request $request): bool
    {
        $list = Cache::remember('visitor.ignore_list.'.LaravelVisitor::resolveConnection(), now()->addMinutes(5), function () {
            return VisitorIgnore::all()
                ->groupBy('type')
                ->map(fn ($items) => $items->pluck('value')->all())
                ->all();
        });

        if ($request->ip() && in_array($request->ip(), $list['ip'] ?? [])) {
            return true;
        }

        if (auth()->check() && in_array((string) auth()->id(), $list['user_id'] ?? [])) {
            return true;
        }

        return false;
    }
}
