<?php

use Illuminate\Support\Facades\Queue;
use RPillz\LaravelVisitor\Jobs\TrackVisitJob;
use RPillz\LaravelVisitor\Models\Visit;
use RPillz\LaravelVisitor\Support\GeoResolver;

beforeEach(function () {
    config([
        'visitor.anonymous' => true,
        'visitor.store_ip' => false,
        'visitor.geoip.database' => '/nonexistent/path/GeoLite2-City.mmdb',
    ]);
});

it('does not pass an ip address to the job when store_ip is false', function () {
    Queue::fake();

    $request = Request::create('https://example.com/about', 'GET');

    app(\RPillz\LaravelVisitor\LaravelVisitor::class)->track($request);

    Queue::assertPushed(TrackVisitJob::class, function (TrackVisitJob $job) {
        return $job->ipAddress === null;
    });
});

it('creates a visit record without touching the geo database', function () {
    $geoResolver = Mockery::mock(GeoResolver::class);
    $geoResolver->shouldNotReceive('resolve');
    app()->instance(GeoResolver::class, $geoResolver);

    TrackVisitJob::dispatchSync(
        dbConnection: config('visitor.connection', 'visitor'),
        url: 'https://example.com/about',
        path: '/about',
        query: null,
        referrer: null,
        ipAddress: null,
        userAgent: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
        sessionId: 'abc123',
        userId: null,
    );

    expect(Visit::count())->toBe(1)
        ->and(Visit::first()->ip_address)->toBeNull()
        ->and(Visit::first()->country)->toBeNull()
        ->and(Visit::first()->city)->toBeNull()
        ->and(Visit::first()->path)->toBe('/about');
});

it('creates a visit record with no user id when anonymous is true', function () {
    TrackVisitJob::dispatchSync(
        dbConnection: config('visitor.connection', 'visitor'),
        url: 'https://example.com/',
        path: '/',
        query: null,
        referrer: null,
        ipAddress: null,
        userAgent: 'Mozilla/5.0',
        sessionId: 'xyz789',
        userId: null,
    );

    expect(Visit::first()->user_id)->toBeNull();
});
