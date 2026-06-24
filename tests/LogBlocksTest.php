<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use RPillz\LaravelVisitor\Http\Middleware\TrackVisit;
use RPillz\LaravelVisitor\Jobs\TrackVisitJob;
use RPillz\LaravelVisitor\Models\Visit;
use RPillz\LaravelVisitor\Models\VisitorIgnore;
use Symfony\Component\HttpFoundation\Response;

beforeEach(function () {
    config([
        'visitor.log_blocks' => true,
        'visitor.block_probes' => true,
        'visitor.probe_paths' => ['wp-admin*'],
        'visitor.store_ip' => true,
        'visitor.anonymous' => true,
        'visitor.deduplication.enabled' => false,
    ]);
    Cache::flush();
    Queue::fake();
});

// --- log_blocks dispatches a job ---

it('dispatches a blocked visit job when a blocked IP is rejected', function () {
    VisitorIgnore::create(['type' => 'ip', 'value' => '1.2.3.4', 'is_blocked' => true]);
    Cache::flush();

    $request = Request::create('https://example.com/about', 'GET', [], [], [], ['REMOTE_ADDR' => '1.2.3.4']);
    app(TrackVisit::class)->handle($request, fn () => new Response('OK', 200));

    Queue::assertPushed(TrackVisitJob::class, fn ($job) => $job->isBlocked === true);
});

it('dispatches a blocked visit job when a probe path is hit', function () {
    $request = Request::create('https://example.com/wp-admin', 'GET', [], [], [], ['REMOTE_ADDR' => '2.2.2.2']);
    app(TrackVisit::class)->handle($request, fn () => new Response('OK', 200));

    Queue::assertPushed(TrackVisitJob::class, fn ($job) => $job->isBlocked === true);
});

it('always stores the IP for blocked visits', function () {
    config(['visitor.store_ip' => false]);

    VisitorIgnore::create(['type' => 'ip', 'value' => '3.3.3.3', 'is_blocked' => true]);
    Cache::flush();

    $request = Request::create('https://example.com/about', 'GET', [], [], [], ['REMOTE_ADDR' => '3.3.3.3']);
    app(TrackVisit::class)->handle($request, fn () => new Response('OK', 200));

    Queue::assertPushed(TrackVisitJob::class, fn ($job) => $job->ipAddress === '3.3.3.3');
});

it('does not dispatch a job when log_blocks is false', function () {
    config(['visitor.log_blocks' => false]);

    VisitorIgnore::create(['type' => 'ip', 'value' => '4.4.4.4', 'is_blocked' => true]);
    Cache::flush();

    $request = Request::create('https://example.com/about', 'GET', [], [], [], ['REMOTE_ADDR' => '4.4.4.4']);
    app(TrackVisit::class)->handle($request, fn () => new Response('OK', 200));

    Queue::assertNotPushed(TrackVisitJob::class);
});

// --- global scope excludes blocked visits ---

it('excludes blocked visits from Visit::query()', function () {
    Visit::withoutGlobalScope('exclude_blocked')->create([
        'url' => 'https://example.com/wp-admin',
        'path' => '/wp-admin',
        'is_blocked' => true,
    ]);
    Visit::withoutGlobalScope('exclude_blocked')->create([
        'url' => 'https://example.com/about',
        'path' => '/about',
        'is_blocked' => false,
    ]);

    expect(Visit::count())->toBe(1)
        ->and(Visit::first()->path)->toBe('/about');
});

it('includes blocked visits when global scope is removed', function () {
    Visit::withoutGlobalScope('exclude_blocked')->create([
        'url' => 'https://example.com/wp-admin',
        'path' => '/wp-admin',
        'is_blocked' => true,
    ]);

    expect(Visit::withoutGlobalScope('exclude_blocked')->count())->toBe(1);
});
