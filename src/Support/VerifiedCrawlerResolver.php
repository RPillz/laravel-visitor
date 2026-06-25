<?php

namespace RPillz\LaravelVisitor\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

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
        return $this->verifyViaRdns($ip) || $this->verifyViaIpLists($ip);
    }

    protected static array $rdnsDomains = [
        'googlebot.com',
        'google.com',
        'search.msn.com',
        'duckduckgo.com',
        'applebot.apple.com',
        'yandex.com',
        'yandex.net',
        'yandex.ru',
        'crawl.baidu.com',
        'petalsearch.com',
        'qwant.com',
    ];

    protected function verifyViaRdns(string $ip): bool
    {
        $hostname = $this->reverseLookup($ip);

        if ($hostname === $ip) {
            return false;
        }

        if ($this->forwardLookup($hostname) !== $ip) {
            return false;
        }

        foreach (static::$rdnsDomains as $domain) {
            if ($hostname === $domain || str_ends_with($hostname, '.'.$domain)) {
                return true;
            }
        }

        return false;
    }

    protected function verifyViaIpLists(string $ip): bool
    {
        foreach (config('visitor.verified_crawlers.ip_lists', []) as $url) {
            foreach ($this->fetchIpList($url) as $cidr) {
                if ($this->isInCidr($ip, $cidr)) {
                    return true;
                }
            }
        }

        return false;
    }

    protected function fetchIpList(string $url): array
    {
        $key = 'visitor.ip_list.'.md5($url);

        if (Cache::has($key)) {
            return Cache::get($key) ?? [];
        }

        try {
            $response = Http::timeout(5)->get($url);

            if (! $response->successful()) {
                Cache::put($key, [], now()->addMinutes(5));

                return [];
            }

            $prefixes = $this->parseIpList($response->json());

            Cache::put($key, $prefixes, now()->addHours(24));

            return $prefixes;
        } catch (\Exception) {
            Cache::put($key, [], now()->addMinutes(5));

            return [];
        }
    }

    protected function parseIpList(mixed $data): array
    {
        if (! is_array($data)) {
            return [];
        }

        // Anthropic format: {"prefixes": [{"ipv4Prefix": "…"}, {"ipv6Prefix": "…"}]}
        if (isset($data['prefixes'])) {
            return collect($data['prefixes'])
                ->map(fn ($p) => $p['ipv4Prefix'] ?? $p['ipv6Prefix'] ?? null)
                ->filter()
                ->values()
                ->all();
        }

        // hexydec format: flat array of {"name": "…", "range": "…", "domain": "…", …}
        return collect($data)
            ->map(fn ($entry) => is_array($entry) ? ($entry['range'] ?? null) : null)
            ->filter()
            ->values()
            ->all();
    }

    protected function isInCidr(string $ip, string $cidr): bool
    {
        if (! str_contains($cidr, '/')) {
            return $ip === $cidr;
        }

        [$subnet, $bits] = explode('/', $cidr, 2);

        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);

        if ($ipLong === false || $subnetLong === false) {
            return false;
        }

        $mask = -1 << (32 - (int) $bits);

        return ($ipLong & $mask) === ($subnetLong & $mask);
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
