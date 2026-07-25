<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use RPillz\LaravelVisitor\Http\Middleware\TrackVisit;
use RPillz\LaravelVisitor\Jobs\TrackVisitJob;
use RPillz\LaravelVisitor\Models\VisitorIgnore;
use Symfony\Component\HttpFoundation\Response;

beforeEach(function () {
    config([
        'visitor.verified_crawlers.enabled' => false,
        'visitor.probe_block_duration' => null,
        'visitor.store_ip' => true,
    ]);
    Cache::flush();
});

// --- Probe-path bypass ---

it('does not auto-block a path that matches both probe_paths and allow_paths', function () {
    config([
        'visitor.probe_paths' => ['feed.xml'],
        'visitor.allow_paths' => ['feed.xml'],
    ]);

    $request = Request::create('https://example.com/feed.xml', 'GET', [], [], [], ['REMOTE_ADDR' => '30.0.0.1']);

    $response = app(TrackVisit::class)->handle($request, fn () => new Response('OK', 200));

    expect($response->getStatusCode())->toBe(200);
    expect(VisitorIgnore::where('type', 'ip')->where('value', '30.0.0.1')->exists())->toBeFalse();
});

it('still blocks a different probe path not covered by allow_paths', function () {
    config([
        'visitor.probe_paths' => ['feed.xml', 'wp-admin*'],
        'visitor.allow_paths' => ['feed.xml'],
    ]);

    $request = Request::create('https://example.com/wp-admin', 'GET', [], [], [], ['REMOTE_ADDR' => '30.0.0.1']);

    $response = app(TrackVisit::class)->handle($request, fn () => new Response('OK', 200));

    expect($response->getStatusCode())->toBe(404);
});

// --- 404 rate-limit bypass ---

it('does not create an auto-block from repeated 404s on an allowed path', function () {
    config([
        'visitor.allow_paths' => ['feed.xml'],
        'visitor.probe_404' => ['threshold' => 3, 'window' => 5],
    ]);

    $request = Request::create('https://example.com/feed.xml', 'GET', [], [], [], ['REMOTE_ADDR' => '30.0.0.2']);
    $middleware = app(TrackVisit::class);

    $middleware->terminate($request, new Response('Not Found', 404));
    $middleware->terminate($request, new Response('Not Found', 404));
    $middleware->terminate($request, new Response('Not Found', 404));

    expect(VisitorIgnore::where('type', 'ip')->where('value', '30.0.0.2')->exists())->toBeFalse();
});

// --- Fingerprint rate-limit bypass ---

it('does not rate-limit an allowed path when the fingerprint exceeded the limit elsewhere', function () {
    config([
        'visitor.allow_paths' => ['feed.xml'],
        'visitor.rate_limit.enabled' => true,
        'visitor.rate_limit.threshold' => 3,
        'visitor.rate_limit.auto_block' => false,
    ]);

    $serverVars = [
        'REMOTE_ADDR' => '30.0.0.4',
        'HTTP_ACCEPT' => 'text/plain',
        'HTTP_ACCEPT_ENCODING' => 'identity',
    ];
    $middleware = app(TrackVisit::class);

    // Same fingerprint (headers), different path — exceeds the threshold on /about.
    $otherRequest = Request::create('https://example.com/about', 'GET', [], [], [], $serverVars);
    for ($i = 0; $i < 4; $i++) {
        $middleware->terminate($otherRequest, new Response('OK', 200));
    }

    $feedRequest = Request::create('https://example.com/feed.xml', 'GET', [], [], [], $serverVars);
    $response = $middleware->handle($feedRequest, fn () => new Response('OK', 200));

    expect($response->getStatusCode())->toBe(200);
});

it('rate-limits a non-allowed path once the fingerprint exceeds the limit elsewhere', function () {
    config([
        'visitor.allow_paths' => [],
        'visitor.rate_limit.enabled' => true,
        'visitor.rate_limit.threshold' => 3,
        'visitor.rate_limit.auto_block' => false,
    ]);

    $serverVars = [
        'REMOTE_ADDR' => '30.0.0.4',
        'HTTP_ACCEPT' => 'text/plain',
        'HTTP_ACCEPT_ENCODING' => 'identity',
    ];
    $middleware = app(TrackVisit::class);

    $otherRequest = Request::create('https://example.com/about', 'GET', [], [], [], $serverVars);
    for ($i = 0; $i < 4; $i++) {
        $middleware->terminate($otherRequest, new Response('OK', 200));
    }

    $feedRequest = Request::create('https://example.com/feed.xml', 'GET', [], [], [], $serverVars);
    $response = $middleware->handle($feedRequest, fn () => new Response('OK', 200));

    expect($response->getStatusCode())->toBe(429);
});

// --- Manual blocks stay authoritative ---

it('still enforces a persistent manual block on an allowed path', function () {
    config(['visitor.allow_paths' => ['feed.xml']]);

    VisitorIgnore::create([
        'type' => 'ip',
        'value' => '30.0.0.5',
        'is_blocked' => true,
        'is_automatic' => false,
        'expires_at' => null,
    ]);
    Cache::flush();

    $request = Request::create('https://example.com/feed.xml', 'GET', [], [], [], ['REMOTE_ADDR' => '30.0.0.5']);

    $response = app(TrackVisit::class)->handle($request, fn () => new Response('OK', 200));

    expect($response->getStatusCode())->toBe(403);
});

// --- Pattern matching ---

it('matches allow_paths wildcard patterns', function () {
    config([
        'visitor.probe_paths' => ['feed*'],
        'visitor.allow_paths' => ['feed*'],
    ]);

    $request = Request::create('https://example.com/feed/episode1.mp3', 'GET', [], [], [], ['REMOTE_ADDR' => '30.0.0.6']);

    $response = app(TrackVisit::class)->handle($request, fn () => new Response('OK', 200));

    expect($response->getStatusCode())->toBe(200);
});

it('does not match an allow_paths pattern with a leading slash', function () {
    config([
        'visitor.probe_paths' => ['feed.xml'],
        'visitor.allow_paths' => ['/feed.xml'],
    ]);

    $request = Request::create('https://example.com/feed.xml', 'GET', [], [], [], ['REMOTE_ADDR' => '30.0.0.7']);

    $response = app(TrackVisit::class)->handle($request, fn () => new Response('OK', 200));

    expect($response->getStatusCode())->toBe(404);
});

// --- Default behavior ---

it('does not affect existing probe blocking when allow_paths is not configured', function () {
    config(['visitor.probe_paths' => ['wp-admin*']]);

    $request = Request::create('https://example.com/wp-admin', 'GET', [], [], [], ['REMOTE_ADDR' => '30.0.0.8']);

    $response = app(TrackVisit::class)->handle($request, fn () => new Response('OK', 200));

    expect($response->getStatusCode())->toBe(404);
});

// --- Tracking is unaffected ---

it('still tracks a normal visit on an allowed path', function () {
    Queue::fake();
    config(['visitor.allow_paths' => ['feed.xml']]);

    $request = Request::create('https://example.com/feed.xml', 'GET', [], [], [], ['REMOTE_ADDR' => '30.0.0.9']);
    $middleware = app(TrackVisit::class);

    $response = $middleware->handle($request, fn () => new Response('OK', 200));
    $middleware->terminate($request, $response);

    Queue::assertPushed(TrackVisitJob::class);
});
