<?php

use Illuminate\Support\Facades\Queue;
use RPillz\LaravelVisitor\Http\Middleware\TrackVisit;
use RPillz\LaravelVisitor\Jobs\TrackVisitJob;
use Symfony\Component\HttpFoundation\Response;

beforeEach(function () {
    config([
        'visitor.anonymous' => true,
        'visitor.store_ip' => false,
        'visitor.track_bots' => false,
        'visitor.exclude_ips' => [],
        'visitor.track_methods' => ['GET'],
        'visitor.deduplication.enabled' => false,
    ]);
    Queue::fake();
});

function makeResponse(int $status = 200): Response
{
    return new Response('OK', $status);
}

it('tracks a normal GET request', function () {
    $request = Request::create('https://example.com/about', 'GET');
    $middleware = app(TrackVisit::class);
    $middleware->terminate($request, makeResponse());

    Queue::assertPushed(TrackVisitJob::class);
});

it('does not track a POST request', function () {
    $request = Request::create('https://example.com/about', 'POST');
    $middleware = app(TrackVisit::class);
    $middleware->terminate($request, makeResponse());

    Queue::assertNotPushed(TrackVisitJob::class);
});

it('does not track excluded paths', function () {
    config(['visitor.exclude_paths' => ['admin*']]);

    $request = Request::create('https://example.com/admin/dashboard', 'GET');
    $middleware = app(TrackVisit::class);
    $middleware->terminate($request, makeResponse());

    Queue::assertNotPushed(TrackVisitJob::class);
});

it('does not track excluded IPs', function () {
    config(['visitor.exclude_ips' => ['1.2.3.4']]);

    $request = Request::create('https://example.com/about', 'GET', [], [], [], ['REMOTE_ADDR' => '1.2.3.4']);
    $middleware = app(TrackVisit::class);
    $middleware->terminate($request, makeResponse());

    Queue::assertNotPushed(TrackVisitJob::class);
});

it('does not track bot user agents', function () {
    $request = Request::create('https://example.com/about', 'GET');
    $request->headers->set('User-Agent', 'Googlebot/2.1 (+http://www.google.com/bot.html)');

    $middleware = app(TrackVisit::class);
    $middleware->terminate($request, makeResponse());

    Queue::assertNotPushed(TrackVisitJob::class);
});

it('does not track 500 responses', function () {
    $request = Request::create('https://example.com/about', 'GET');
    $middleware = app(TrackVisit::class);
    $middleware->terminate($request, makeResponse(500));

    Queue::assertNotPushed(TrackVisitJob::class);
});

it('tracks when method is in track_methods config', function () {
    config(['visitor.track_methods' => ['GET', 'POST']]);

    $request = Request::create('https://example.com/track', 'POST');
    $middleware = app(TrackVisit::class);
    $middleware->terminate($request, makeResponse());

    Queue::assertPushed(TrackVisitJob::class);
});
