<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use RPillz\LaravelVisitor\Http\Middleware\TrackVisit;
use RPillz\LaravelVisitor\Models\VisitorIgnore;
use RPillz\LaravelVisitor\Support\VerifiedCrawlerResolver;
use Symfony\Component\HttpFoundation\Response;

beforeEach(function () {
    config([
        'visitor.block_probes' => false,
        'visitor.block_verified_bots' => [],
        'visitor.block_unverified_bots' => false,
        'visitor.rate_limit.enabled' => true,
        'visitor.rate_limit.threshold' => 3,
        'visitor.rate_limit.window' => 1,
        'visitor.rate_limit.auto_block' => true,
        'visitor.probe_block_duration' => null,
        'visitor.verified_crawlers.enabled' => false,
    ]);
    Cache::flush();
});

function makeRateLimitRequest(string $accept = 'text/plain'): Request
{
    return Request::create('https://example.com/about', 'GET', [], [], [], [
        'REMOTE_ADDR' => '1.2.3.4',
        'HTTP_ACCEPT' => $accept,
        'HTTP_ACCEPT_ENCODING' => 'identity',
    ]);
}

// --- Rate limit enforcement ---

it('allows requests below the threshold', function () {
    // auto_block disabled so VisitorIgnore is not created; we test only the rate limiter path.
    config(['visitor.rate_limit.auto_block' => false]);

    $middleware = app(TrackVisit::class);
    $request = makeRateLimitRequest();

    // 2 hits — below threshold of 3.
    for ($i = 0; $i < 2; $i++) {
        $middleware->terminate($request, new Response('OK', 200));
    }

    $response = $middleware->handle($request, fn () => new Response('OK', 200));
    expect($response->getStatusCode())->toBe(200);
});

it('returns 429 when a fingerprint exceeds the rate limit', function () {
    // auto_block disabled so VisitorIgnore is not written; isBlocked() does not
    // intercept and the 429 comes from the rate limiter check in handle().
    config(['visitor.rate_limit.auto_block' => false]);

    $middleware = app(TrackVisit::class);
    $request = makeRateLimitRequest();

    // Hit the threshold exactly.
    for ($i = 0; $i < 3; $i++) {
        $middleware->terminate($request, new Response('OK', 200));
    }

    $response = $middleware->handle($request, fn () => new Response('OK', 200));

    expect($response->getStatusCode())->toBe(429);
});

it('does not rate-limit when rate_limit is disabled', function () {
    config(['visitor.rate_limit.enabled' => false]);

    $middleware = app(TrackVisit::class);
    $request = makeRateLimitRequest();

    for ($i = 0; $i < 10; $i++) {
        $middleware->terminate($request, new Response('OK', 200));
    }

    $response = $middleware->handle($request, fn () => new Response('OK', 200));

    expect($response->getStatusCode())->toBe(200);
});

it('counts requests separately per fingerprint', function () {
    $middleware = app(TrackVisit::class);
    $requestA = makeRateLimitRequest('text/plain');
    $requestB = makeRateLimitRequest('application/json'); // different accept → different fingerprint

    // Hit requestA threshold.
    for ($i = 0; $i < 4; $i++) {
        $middleware->terminate($requestA, new Response('OK', 200));
    }

    // requestB should still be within its own threshold.
    $response = $middleware->handle($requestB, fn () => new Response('OK', 200));

    expect($response->getStatusCode())->toBe(200);
});

// --- Auto-block ---

it('writes a VisitorIgnore entry when auto_block is true and threshold is exceeded', function () {
    $middleware = app(TrackVisit::class);
    $request = makeRateLimitRequest();

    for ($i = 0; $i < 4; $i++) {
        $middleware->terminate($request, new Response('OK', 200));
    }

    expect(VisitorIgnore::where('type', 'header_fingerprint')->where('is_blocked', true)->exists())->toBeTrue();
});

it('does not write a VisitorIgnore entry when auto_block is false', function () {
    config(['visitor.rate_limit.auto_block' => false]);

    $middleware = app(TrackVisit::class);
    $request = makeRateLimitRequest();

    for ($i = 0; $i < 4; $i++) {
        $middleware->terminate($request, new Response('OK', 200));
    }

    expect(VisitorIgnore::where('type', 'header_fingerprint')->where('is_blocked', true)->exists())->toBeFalse();
});

it('does not count 429 responses toward the rate limit', function () {
    $middleware = app(TrackVisit::class);
    $request = makeRateLimitRequest();

    // Exceed threshold.
    for ($i = 0; $i < 4; $i++) {
        $middleware->terminate($request, new Response('OK', 200));
    }

    // These 429 responses should not add to the counter.
    $middleware->terminate($request, new Response('Too Many Requests', 429));
    $middleware->terminate($request, new Response('Too Many Requests', 429));

    // The block entry should exist with is_automatic=true, same as if we only hit it once.
    $entries = VisitorIgnore::where('type', 'header_fingerprint')->where('is_blocked', true)->get();
    expect($entries->count())->toBe(1);
});

// --- Verified crawlers bypass ---

it('does not rate-limit verified crawlers', function () {
    config(['visitor.verified_crawlers.enabled' => true]);

    app()->instance(VerifiedCrawlerResolver::class, new class extends VerifiedCrawlerResolver {
        public function isVerified(Request $request): bool { return true; }
    });

    $middleware = app(TrackVisit::class);
    $request = makeRateLimitRequest();

    for ($i = 0; $i < 10; $i++) {
        $middleware->terminate($request, new Response('OK', 200));
    }

    $response = $middleware->handle($request, fn () => new Response('OK', 200));

    expect($response->getStatusCode())->toBe(200);
    expect(VisitorIgnore::where('type', 'header_fingerprint')->where('is_blocked', true)->exists())->toBeFalse();
});
