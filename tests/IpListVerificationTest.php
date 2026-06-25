<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RPillz\LaravelVisitor\Support\VerifiedCrawlerResolver;

beforeEach(function () {
    config([
        'visitor.verified_crawlers.enabled' => true,
        'visitor.verified_crawlers.cache_ttl' => 1440,
        'visitor.verified_crawlers.ip_lists' => ['https://example.com/bots.json'],
    ]);
    Cache::flush();
    Http::preventStrayRequests();
});

function fakeIpList(array $ipv4Prefixes = [], int $status = 200): void
{
    Http::fake([
        'https://example.com/bots.json' => Http::response([
            'creationTime' => '2026-06-01T00:00:00Z',
            'prefixes' => array_map(fn ($p) => ['ipv4Prefix' => $p], $ipv4Prefixes),
        ], $status),
    ]);
}

// --- Basic IP list matching ---

it('verifies an IP that exactly matches a /32 prefix', function () {
    fakeIpList(['192.168.1.1/32']);

    $resolver = new VerifiedCrawlerResolver;
    $request = Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => '192.168.1.1']);

    expect($resolver->isVerified($request))->toBeTrue();
});

it('verifies an IP within a CIDR subnet', function () {
    fakeIpList(['10.0.0.0/24']);

    $resolver = new VerifiedCrawlerResolver;
    $request = Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => '10.0.0.100']);

    expect($resolver->isVerified($request))->toBeTrue();
});

it('does not verify an IP outside the CIDR subnet', function () {
    fakeIpList(['10.0.0.0/24']);

    $resolver = new VerifiedCrawlerResolver;
    $request = Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => '10.0.1.1']);

    expect($resolver->isVerified($request))->toBeFalse();
});

it('does not verify an IP not present in the list', function () {
    fakeIpList(['216.73.216.0/22']);

    $resolver = new VerifiedCrawlerResolver;
    $request = Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => '1.2.3.4']);

    expect($resolver->isVerified($request))->toBeFalse();
});

it('verifies an IP within a /22 subnet', function () {
    fakeIpList(['216.73.216.0/22']);

    $resolver = new VerifiedCrawlerResolver;

    // 216.73.216.0/22 covers 216.73.216.0 – 216.73.219.255
    $request = Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => '216.73.218.50']);
    expect($resolver->isVerified($request))->toBeTrue();

    Cache::flush();

    $request2 = Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => '216.73.220.1']);
    expect($resolver->isVerified($request2))->toBeFalse();
});

// --- Caching behaviour ---

it('caches the fetched IP list and only calls the URL once', function () {
    Http::fake([
        'https://example.com/bots.json' => Http::sequence()
            ->push(['prefixes' => [['ipv4Prefix' => '1.2.3.4/32']]], 200)
            ->push(['prefixes' => []], 200), // second call would return empty
    ]);

    $resolver = new VerifiedCrawlerResolver;
    $r1 = Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => '1.2.3.4']);
    $r2 = Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => '5.6.7.8']);

    // Both resolutions should use the same cached IP list.
    expect($resolver->isVerified($r1))->toBeTrue();
    expect($resolver->isVerified($r2))->toBeFalse();

    Http::assertSentCount(1);
});

it('retries the IP list after a short ttl on HTTP failure', function () {
    Http::fake([
        'https://example.com/bots.json' => Http::sequence()
            ->push([], 500)
            ->push(['prefixes' => [['ipv4Prefix' => '1.2.3.4/32']]], 200),
    ]);

    $resolver = new VerifiedCrawlerResolver;
    $request = Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => '1.2.3.4']);

    // First call — server returns 500, caches empty list for 5 minutes.
    expect($resolver->isVerified($request))->toBeFalse();

    // Flush the short-TTL cache entry to simulate time passing.
    Cache::forget('visitor.ip_list.'.md5('https://example.com/bots.json'));
    Cache::forget('visitor.verified_crawler.1.2.3.4');

    // Second call — server now returns the correct list.
    expect($resolver->isVerified($request))->toBeTrue();
});

it('handles an exception during IP list fetch gracefully', function () {
    Http::fake([
        'https://example.com/bots.json' => fn () => throw new \Exception('connection refused'),
    ]);

    $resolver = new VerifiedCrawlerResolver;
    $request = Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => '1.2.3.4']);

    expect($resolver->isVerified($request))->toBeFalse();
});

// --- Disabled ---

it('returns false without hitting the IP list when verified_crawlers is disabled', function () {
    config(['visitor.verified_crawlers.enabled' => false]);

    Http::fake(); // any request would fail the test

    $resolver = new VerifiedCrawlerResolver;
    $request = Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => '1.2.3.4']);

    expect($resolver->isVerified($request))->toBeFalse();
    Http::assertNothingSent();
});

// --- Anthropic bots.json format ---

it('parses the Anthropic bots.json prefix structure', function () {
    Http::fake([
        'https://example.com/bots.json' => Http::response([
            'creationTime' => '2026-05-01T20:46:04Z',
            'prefixes' => [
                ['ipv4Prefix' => '216.73.216.0/22'],
                ['ipv4Prefix' => '34.162.230.222/32'],
            ],
        ], 200),
    ]);

    $resolver = new VerifiedCrawlerResolver;

    $inRange = Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => '216.73.217.10']);
    expect($resolver->isVerified($inRange))->toBeTrue();

    Cache::flush();

    $exact = Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => '34.162.230.222']);
    expect($resolver->isVerified($exact))->toBeTrue();
});

// --- hexydec/ip-ranges format ---

it('parses the hexydec crawlers.json flat array format', function () {
    Http::fake([
        'https://example.com/bots.json' => Http::response([
            ['name' => 'ClaudeBot', 'range' => '160.79.104.0/23', 'domain' => 'anthropic.com', 'url' => 'https://www.anthropic.com', 'match' => 'ClaudeBot'],
            ['name' => 'ClaudeBot', 'range' => '34.162.46.92/32', 'domain' => 'anthropic.com', 'url' => 'https://www.anthropic.com', 'match' => 'ClaudeBot'],
            ['name' => 'BingBot', 'range' => '40.77.167.0/24', 'domain' => 'search.msn.com', 'url' => 'https://www.bing.com/webmaster', 'match' => 'bingbot'],
        ], 200),
    ]);

    $resolver = new VerifiedCrawlerResolver;

    $claudeInSubnet = Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => '160.79.104.50']);
    expect($resolver->isVerified($claudeInSubnet))->toBeTrue();

    Cache::flush();

    $claudeExact = Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => '34.162.46.92']);
    expect($resolver->isVerified($claudeExact))->toBeTrue();

    Cache::flush();

    $bingInSubnet = Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => '40.77.167.100']);
    expect($resolver->isVerified($bingInSubnet))->toBeTrue();

    Cache::flush();

    $unknown = Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => '1.2.3.4']);
    expect($resolver->isVerified($unknown))->toBeFalse();
});

it('ignores entries with missing range fields in hexydec format', function () {
    Http::fake([
        'https://example.com/bots.json' => Http::response([
            ['name' => 'ClaudeBot', 'domain' => 'anthropic.com'], // no range field
            ['name' => 'BingBot', 'range' => '40.77.167.0/24', 'domain' => 'search.msn.com', 'match' => 'bingbot'],
        ], 200),
    ]);

    $resolver = new VerifiedCrawlerResolver;

    $bing = Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => '40.77.167.50']);
    expect($resolver->isVerified($bing))->toBeTrue();
});
