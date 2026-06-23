<?php

namespace RPillz\LaravelVisitor;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use RPillz\LaravelVisitor\Jobs\TrackVisitJob;

class LaravelVisitor
{
    protected bool $forceAnonymous = false;

    protected ?string $pendingConnection = null;

    protected static ?Closure $connectionResolver = null;

    public static function resolveConnectionUsing(Closure $resolver): void
    {
        static::$connectionResolver = $resolver;
    }

    public static function resolveConnection(): string
    {
        if (static::$connectionResolver) {
            return (static::$connectionResolver)();
        }

        return config('visitor.connection', 'visitor');
    }

    public function anonymous(): static
    {
        $clone = clone $this;
        $clone->forceAnonymous = true;

        return $clone;
    }

    public function setConnection(string $name): static
    {
        $clone = clone $this;
        $clone->pendingConnection = $name;

        return $clone;
    }

    public function track(Request $request): void
    {
        $connection = $this->pendingConnection ?? static::resolveConnection();
        $this->pendingConnection = null;

        $anonymous = $this->forceAnonymous || config('visitor.anonymous', false);

        $sessionId = $request->hasSession() ? $request->session()->getId() : null;
        $path = '/'.ltrim($request->path(), '/');

        if ($this->isDuplicate($sessionId, $path)) {
            return;
        }

        TrackVisitJob::dispatch(
            dbConnection: $connection,
            url: $request->fullUrl(),
            path: $path,
            query: $request->getQueryString(),
            referrer: $request->header('referer'),
            ipAddress: config('visitor.store_ip', true) ? $request->ip() : null,
            userAgent: $request->userAgent(),
            sessionId: $sessionId,
            userId: (! $anonymous && auth()->check()) ? auth()->id() : null,
        )
            ->onConnection(config('visitor.queue.connection'))
            ->onQueue(config('visitor.queue.name', 'default'));
    }

    protected function isDuplicate(?string $sessionId, string $path): bool
    {
        if (! $sessionId || ! config('visitor.deduplication.enabled', true)) {
            return false;
        }

        $key = 'visitor.dedup.'.md5($sessionId.$path);
        $window = (int) config('visitor.deduplication.window', 30);

        if (Cache::has($key)) {
            return true;
        }

        Cache::put($key, true, now()->addMinutes($window));

        return false;
    }
}
