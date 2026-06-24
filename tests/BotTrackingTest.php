<?php

use Illuminate\Support\Facades\Queue;
use RPillz\LaravelVisitor\Http\Middleware\TrackVisit;
use RPillz\LaravelVisitor\Jobs\TrackVisitJob;
use RPillz\LaravelVisitor\Models\Visit;
use Symfony\Component\HttpFoundation\Response;

const GOOGLEBOT_UA = 'Googlebot/2.1 (+http://www.google.com/bot.html)';

beforeEach(function () {
    config([
        'visitor.anonymous' => true,
        'visitor.store_ip' => false,
        'visitor.exclude_ips' => [],
        'visitor.track_methods' => ['GET'],
        'visitor.deduplication.enabled' => false,
        'visitor.geoip.database' => '/nonexistent/path/GeoLite2-City.mmdb',
    ]);
});

// --- Middleware dispatch ---

it('dispatches a job for a bot when track_bots is true', function () {
    Queue::fake();
    config(['visitor.track_bots' => true]);

    $request = Request::create('https://example.com/about', 'GET');
    $request->headers->set('User-Agent', GOOGLEBOT_UA);

    app(TrackVisit::class)->terminate($request, new Response('OK', 200));

    Queue::assertPushed(TrackVisitJob::class);
});

it('does not dispatch a job for a bot when track_bots is false', function () {
    Queue::fake();
    config(['visitor.track_bots' => false]);

    $request = Request::create('https://example.com/about', 'GET');
    $request->headers->set('User-Agent', GOOGLEBOT_UA);

    app(TrackVisit::class)->terminate($request, new Response('OK', 200));

    Queue::assertNotPushed(TrackVisitJob::class);
});

// --- Visit record ---

it('stores bot_name and user_agent on the visit record for a bot', function () {
    TrackVisitJob::dispatchSync(
        dbConnection: config('visitor.connection', 'visitor'),
        url: 'https://example.com/about',
        path: '/about',
        query: null,
        referrer: null,
        ipAddress: null,
        userAgent: GOOGLEBOT_UA,
        sessionId: null,
        isUser: false,
        userId: null,
    );

    $visit = Visit::first();
    expect($visit->user_agent)->toBe(GOOGLEBOT_UA)
        ->and($visit->bot_name)->not->toBeNull();
});

it('stores null bot_name for a human visit', function () {
    TrackVisitJob::dispatchSync(
        dbConnection: config('visitor.connection', 'visitor'),
        url: 'https://example.com/about',
        path: '/about',
        query: null,
        referrer: null,
        ipAddress: null,
        userAgent: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
        sessionId: null,
        isUser: false,
        userId: null,
    );

    expect(Visit::first()->bot_name)->toBeNull();
});

it('stores the raw user_agent string on the visit record', function () {
    $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36';

    TrackVisitJob::dispatchSync(
        dbConnection: config('visitor.connection', 'visitor'),
        url: 'https://example.com/',
        path: '/',
        query: null,
        referrer: null,
        ipAddress: null,
        userAgent: $ua,
        sessionId: null,
        isUser: false,
        userId: null,
    );

    expect(Visit::first()->user_agent)->toBe($ua);
});

// --- is_user flag ---

it('stores is_user true on the visit record when isUser is true', function () {
    TrackVisitJob::dispatchSync(
        dbConnection: config('visitor.connection', 'visitor'),
        url: 'https://example.com/',
        path: '/',
        query: null,
        referrer: null,
        ipAddress: null,
        userAgent: 'Mozilla/5.0',
        sessionId: null,
        isUser: true,
        userId: null,
    );

    expect(Visit::first()->is_user)->toBeTrue();
});

it('stores is_user false on the visit record when isUser is false', function () {
    TrackVisitJob::dispatchSync(
        dbConnection: config('visitor.connection', 'visitor'),
        url: 'https://example.com/',
        path: '/',
        query: null,
        referrer: null,
        ipAddress: null,
        userAgent: 'Mozilla/5.0',
        sessionId: null,
        isUser: false,
        userId: null,
    );

    expect(Visit::first()->is_user)->toBeFalse();
});
