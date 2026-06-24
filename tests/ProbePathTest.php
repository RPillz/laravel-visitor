<?php

use Illuminate\Support\Facades\Cache;
use RPillz\LaravelVisitor\Http\Middleware\TrackVisit;
use RPillz\LaravelVisitor\Models\VisitorIgnore;
use Symfony\Component\HttpFoundation\Response;

beforeEach(function () {
    config([
        'visitor.probe_paths' => ['wp-admin*', '.env*'],
        'visitor.probe_block_duration' => null,
        'visitor.store_ip' => true,
    ]);
    Cache::flush();
});

// --- Probe detection ---

it('returns 403 when a probe path is accessed', function () {
    $request = Request::create('https://example.com/wp-admin', 'GET', [], [], [], ['REMOTE_ADDR' => '1.2.3.4']);

    $response = app(TrackVisit::class)->handle($request, fn () => new Response('OK', 200));

    expect($response->getStatusCode())->toBe(403);
});

it('returns 403 for probe paths with wildcard suffix', function () {
    $request = Request::create('https://example.com/wp-admin/setup-config.php', 'GET', [], [], [], ['REMOTE_ADDR' => '1.2.3.4']);

    $response = app(TrackVisit::class)->handle($request, fn () => new Response('OK', 200));

    expect($response->getStatusCode())->toBe(403);
});

it('does not block normal paths when probe_paths are configured', function () {
    $request = Request::create('https://example.com/about', 'GET', [], [], [], ['REMOTE_ADDR' => '1.2.3.4']);

    $response = app(TrackVisit::class)->handle($request, fn () => new Response('OK', 200));

    expect($response->getStatusCode())->toBe(200);
});

it('does not block when probe_paths is empty', function () {
    config(['visitor.probe_paths' => []]);

    $request = Request::create('https://example.com/wp-admin', 'GET', [], [], [], ['REMOTE_ADDR' => '1.2.3.4']);

    $response = app(TrackVisit::class)->handle($request, fn () => new Response('OK', 200));

    expect($response->getStatusCode())->toBe(200);
});

it('does not block probe paths when block_probes is false', function () {
    config(['visitor.block_probes' => false]);

    $request = Request::create('https://example.com/wp-admin', 'GET', [], [], [], ['REMOTE_ADDR' => '1.2.3.4']);

    $response = app(TrackVisit::class)->handle($request, fn () => new Response('OK', 200));

    expect($response->getStatusCode())->toBe(200);
    expect(VisitorIgnore::where('type', 'ip')->where('value', '1.2.3.4')->exists())->toBeFalse();
});

// --- Auto-blocking ---

it('creates a blocked VisitorIgnore entry when a probe path is hit', function () {
    $request = Request::create('https://example.com/wp-admin', 'GET', [], [], [], ['REMOTE_ADDR' => '5.5.5.5']);

    app(TrackVisit::class)->handle($request, fn () => new Response('OK', 200));

    $entry = VisitorIgnore::where('type', 'ip')->where('value', '5.5.5.5')->first();
    expect($entry)->not->toBeNull()
        ->and($entry->is_blocked)->toBeTrue()
        ->and($entry->is_automatic)->toBeTrue();
});

it('creates a permanent block when probe_block_duration is null', function () {
    $request = Request::create('https://example.com/.env', 'GET', [], [], [], ['REMOTE_ADDR' => '6.6.6.6']);

    app(TrackVisit::class)->handle($request, fn () => new Response('OK', 200));

    $entry = VisitorIgnore::where('type', 'ip')->where('value', '6.6.6.6')->first();
    expect($entry->expires_at)->toBeNull();
});

it('creates a temporary block when probe_block_duration is set', function () {
    config(['visitor.probe_block_duration' => 60]);

    $request = Request::create('https://example.com/wp-admin', 'GET', [], [], [], ['REMOTE_ADDR' => '7.7.7.7']);

    app(TrackVisit::class)->handle($request, fn () => new Response('OK', 200));

    $entry = VisitorIgnore::where('type', 'ip')->where('value', '7.7.7.7')->first();
    expect($entry->expires_at)->not->toBeNull()
        ->and($entry->expires_at->isFuture())->toBeTrue();
});

it('does not create duplicate auto-block entries on repeated probe hits', function () {
    $request = Request::create('https://example.com/wp-admin', 'GET', [], [], [], ['REMOTE_ADDR' => '8.8.8.8']);
    $middleware = app(TrackVisit::class);

    $middleware->handle($request, fn () => new Response('OK', 200));
    Cache::flush();
    $middleware->handle($request, fn () => new Response('OK', 200));

    expect(VisitorIgnore::where('type', 'ip')->where('value', '8.8.8.8')->count())->toBe(1);
});

// --- Expiry ---

it('does not block an IP whose block has expired', function () {
    VisitorIgnore::create([
        'type' => 'ip',
        'value' => '9.9.9.9',
        'is_blocked' => true,
        'is_automatic' => true,
        'expires_at' => now()->subMinute(),
    ]);
    Cache::flush();

    $request = Request::create('https://example.com/about', 'GET', [], [], [], ['REMOTE_ADDR' => '9.9.9.9']);

    $response = app(TrackVisit::class)->handle($request, fn () => new Response('OK', 200));

    expect($response->getStatusCode())->toBe(200);
});

it('still blocks an IP whose block has not yet expired', function () {
    VisitorIgnore::create([
        'type' => 'ip',
        'value' => '10.0.0.1',
        'is_blocked' => true,
        'is_automatic' => true,
        'expires_at' => now()->addHour(),
    ]);
    Cache::flush();

    $request = Request::create('https://example.com/about', 'GET', [], [], [], ['REMOTE_ADDR' => '10.0.0.1']);

    $response = app(TrackVisit::class)->handle($request, fn () => new Response('OK', 200));

    expect($response->getStatusCode())->toBe(403);
});

it('refreshes an expired auto-block when the IP probes again', function () {
    config(['visitor.probe_block_duration' => 60]);

    VisitorIgnore::create([
        'type' => 'ip',
        'value' => '11.0.0.1',
        'is_blocked' => true,
        'is_automatic' => true,
        'expires_at' => now()->subMinute(),
    ]);
    Cache::flush();

    $request = Request::create('https://example.com/wp-admin', 'GET', [], [], [], ['REMOTE_ADDR' => '11.0.0.1']);
    app(TrackVisit::class)->handle($request, fn () => new Response('OK', 200));

    $entry = VisitorIgnore::where('type', 'ip')->where('value', '11.0.0.1')->first();
    expect($entry->expires_at->isFuture())->toBeTrue();
});

// --- 404 rate limiting ---

it('does not block an IP after a single 404', function () {
    config(['visitor.probe_404' => ['threshold' =>5, 'window' => 5]]);

    $request = Request::create('https://example.com/missing', 'GET', [], [], [], ['REMOTE_ADDR' => '20.0.0.1']);
    app(TrackVisit::class)->terminate($request, new Response('Not Found', 404));

    expect(VisitorIgnore::where('type', 'ip')->where('value', '20.0.0.1')->exists())->toBeFalse();
});

it('blocks an IP that reaches the 404 threshold', function () {
    config(['visitor.probe_404' => ['threshold' =>3, 'window' => 5]]);

    $request = Request::create('https://example.com/missing', 'GET', [], [], [], ['REMOTE_ADDR' => '20.0.0.2']);
    $middleware = app(TrackVisit::class);

    $middleware->terminate($request, new Response('Not Found', 404));
    $middleware->terminate($request, new Response('Not Found', 404));
    $middleware->terminate($request, new Response('Not Found', 404));

    $entry = VisitorIgnore::where('type', 'ip')->where('value', '20.0.0.2')->first();
    expect($entry)->not->toBeNull()
        ->and($entry->is_blocked)->toBeTrue()
        ->and($entry->is_automatic)->toBeTrue();
});

it('tracks 404 counts separately per IP', function () {
    config(['visitor.probe_404' => ['threshold' =>3, 'window' => 5]]);

    $middleware = app(TrackVisit::class);
    $ipA = Request::create('https://example.com/missing', 'GET', [], [], [], ['REMOTE_ADDR' => '20.0.0.3']);
    $ipB = Request::create('https://example.com/missing', 'GET', [], [], [], ['REMOTE_ADDR' => '20.0.0.4']);

    $middleware->terminate($ipA, new Response('Not Found', 404));
    $middleware->terminate($ipA, new Response('Not Found', 404));
    $middleware->terminate($ipB, new Response('Not Found', 404));

    expect(VisitorIgnore::where('type', 'ip')->where('value', '20.0.0.3')->exists())->toBeFalse()
        ->and(VisitorIgnore::where('type', 'ip')->where('value', '20.0.0.4')->exists())->toBeFalse();
});

it('does not count non-404 responses toward the 404 threshold', function () {
    config(['visitor.probe_404' => ['threshold' =>3, 'window' => 5]]);

    $request = Request::create('https://example.com/page', 'GET', [], [], [], ['REMOTE_ADDR' => '20.0.0.5']);
    $middleware = app(TrackVisit::class);

    $middleware->terminate($request, new Response('OK', 200));
    $middleware->terminate($request, new Response('OK', 200));
    $middleware->terminate($request, new Response('Server Error', 500));

    expect(VisitorIgnore::where('type', 'ip')->where('value', '20.0.0.5')->exists())->toBeFalse();
});

// --- Fingerprint blocking ---

it('creates a header_fingerprint block entry when a probe path is hit', function () {
    $request = Request::create('https://example.com/wp-admin', 'GET', [], [], [], ['REMOTE_ADDR' => '5.5.5.5']);

    app(TrackVisit::class)->handle($request, fn () => new Response('OK', 200));

    expect(VisitorIgnore::where('type', 'header_fingerprint')->where('is_blocked', true)->exists())->toBeTrue();
});

it('blocks a request with a blocked fingerprint regardless of IP', function () {
    $serverVars = ['HTTP_ACCEPT' => 'text/plain', 'HTTP_ACCEPT_LANGUAGE' => '', 'HTTP_ACCEPT_ENCODING' => 'identity'];

    $firstRequest = Request::create('https://example.com/wp-admin', 'GET', [], [], [], array_merge(['REMOTE_ADDR' => '1.1.1.1'], $serverVars));
    app(TrackVisit::class)->handle($firstRequest, fn () => new Response('OK', 200));
    Cache::flush();

    $secondRequest = Request::create('https://example.com/about', 'GET', [], [], [], array_merge(['REMOTE_ADDR' => '2.2.2.2'], $serverVars));
    $response = app(TrackVisit::class)->handle($secondRequest, fn () => new Response('OK', 200));

    expect($response->getStatusCode())->toBe(403);
});

it('does not block a request with a different fingerprint from a blocked one', function () {
    $serverVars = ['HTTP_ACCEPT' => 'text/plain', 'HTTP_ACCEPT_ENCODING' => 'identity'];

    $probeRequest = Request::create('https://example.com/wp-admin', 'GET', [], [], [], array_merge(['REMOTE_ADDR' => '3.3.3.3'], $serverVars));
    app(TrackVisit::class)->handle($probeRequest, fn () => new Response('OK', 200));
    Cache::flush();

    $browserRequest = Request::create('https://example.com/about', 'GET', [], [], [], [
        'REMOTE_ADDR' => '4.4.4.4',
        'HTTP_ACCEPT' => 'text/html,application/xhtml+xml',
        'HTTP_ACCEPT_LANGUAGE' => 'en-US,en;q=0.9',
        'HTTP_ACCEPT_ENCODING' => 'gzip, deflate, br',
    ]);
    $response = app(TrackVisit::class)->handle($browserRequest, fn () => new Response('OK', 200));

    expect($response->getStatusCode())->toBe(200);
});

it('does not track 404s when block_probes is false', function () {
    config([
        'visitor.block_probes' => false,
        'visitor.probe_404' => ['threshold' =>1, 'window' => 5],
    ]);

    $request = Request::create('https://example.com/missing', 'GET', [], [], [], ['REMOTE_ADDR' => '20.0.0.7']);
    app(TrackVisit::class)->terminate($request, new Response('Not Found', 404));

    expect(VisitorIgnore::where('type', 'ip')->where('value', '20.0.0.7')->exists())->toBeFalse();
});
