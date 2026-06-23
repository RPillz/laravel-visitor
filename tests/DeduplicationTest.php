<?php

use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use RPillz\LaravelVisitor\Jobs\TrackVisitJob;
use RPillz\LaravelVisitor\LaravelVisitor;

beforeEach(function () {
    config([
        'visitor.anonymous' => true,
        'visitor.store_ip' => false,
        'visitor.deduplication.enabled' => true,
        'visitor.deduplication.window' => 30,
    ]);
    Queue::fake();
    Cache::flush();
});

function makeSession(): Store
{
    return new Store('test', new ArraySessionHandler(100));
}

function makeRequest(string $path = '/about', ?Store $session = null)
{
    $request = Request::create('https://example.com'.$path, 'GET');
    if ($session) {
        $request->setLaravelSession($session);
    }

    return $request;
}

it('dispatches a job on the first visit', function () {
    $request = makeRequest('/about', makeSession());

    app(LaravelVisitor::class)->track($request);

    Queue::assertPushed(TrackVisitJob::class);
});

it('does not dispatch a second job for the same session and path within the window', function () {
    $session = makeSession();
    $visitor = app(LaravelVisitor::class);

    $visitor->track(makeRequest('/about', $session));
    $visitor->track(makeRequest('/about', $session));

    Queue::assertPushed(TrackVisitJob::class, 1);
});

it('dispatches again for a different path in the same session', function () {
    $session = makeSession();
    $visitor = app(LaravelVisitor::class);

    $visitor->track(makeRequest('/about', $session));
    $visitor->track(makeRequest('/contact', $session));

    Queue::assertPushed(TrackVisitJob::class, 2);
});

it('dispatches again after the dedup window expires', function () {
    $session = makeSession();
    $visitor = app(LaravelVisitor::class);

    $visitor->track(makeRequest('/about', $session));

    Cache::flush(); // simulate TTL expiry

    $visitor->track(makeRequest('/about', $session));

    Queue::assertPushed(TrackVisitJob::class, 2);
});

it('skips deduplication for requests with no session', function () {
    $visitor = app(LaravelVisitor::class);

    $visitor->track(makeRequest('/about'));
    $visitor->track(makeRequest('/about'));

    Queue::assertPushed(TrackVisitJob::class, 2);
});
