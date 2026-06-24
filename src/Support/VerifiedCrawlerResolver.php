<?php

namespace RPillz\LaravelVisitor\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class VerifiedCrawlerResolver
{
    public function isVerified(Request $request): bool
    {
        if (! config('visitor.verified_crawlers.enabled', true)) {
            return false;
        }

        $ip = $request->ip();
        if (! $ip) {
            return false;
        }

        $ttl = (int) config('visitor.verified_crawlers.cache_ttl', 1440);

        return Cache::remember(
            'visitor.verified_crawler.'.$ip,
            now()->addMinutes($ttl),
            fn () => $this->verify($ip)
        );
    }

    protected function verify(string $ip): bool
    {
        $hostname = $this->reverseLookup($ip);

        if ($hostname === $ip) {
            return false;
        }

        if ($this->forwardLookup($hostname) !== $ip) {
            return false;
        }

        foreach (config('visitor.verified_crawlers.domains', []) as $domain) {
            if ($hostname === $domain || str_ends_with($hostname, '.'.$domain)) {
                return true;
            }
        }

        return false;
    }

    protected function reverseLookup(string $ip): string
    {
        return gethostbyaddr($ip);
    }

    protected function forwardLookup(string $hostname): string
    {
        return gethostbyname($hostname);
    }
}
