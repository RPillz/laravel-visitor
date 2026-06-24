<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use RPillz\LaravelVisitor\Http\Middleware\TrackVisit;
use RPillz\LaravelVisitor\Jobs\TrackVisitJob;
use RPillz\LaravelVisitor\LaravelVisitor;
use RPillz\LaravelVisitor\Models\Visit;
use RPillz\LaravelVisitor\Models\VisitorIgnore;
use Symfony\Component\HttpFoundation\Response;

beforeEach(function () {
    config([
        'visitor.anonymous' => true,
        'visitor.store_ip' => true,
        'visitor.track_bots' => true,
        'visitor.exclude_ips' => [],
        'visitor.track_methods' => ['GET'],
        'visitor.deduplication.enabled' => false,
    ]);
    Cache::flush();
    Queue::fake();
});

function makeOkResponse(): Response
{
    return new Response('OK', 200);
}

// --- Middleware: IP ignore ---

it('does not track a request from an ignored IP', function () {
    VisitorIgnore::create(['type' => 'ip', 'value' => '1.2.3.4']);
    Cache::flush(); // bypass the 5-minute cache so the new entry is picked up

    $request = Request::create('https://example.com/about', 'GET', [], [], [], ['REMOTE_ADDR' => '1.2.3.4']);
    app(TrackVisit::class)->terminate($request, makeOkResponse());

    Queue::assertNotPushed(TrackVisitJob::class);
});

it('still tracks a request from a non-ignored IP', function () {
    VisitorIgnore::create(['type' => 'ip', 'value' => '1.2.3.4']);
    Cache::flush();

    $request = Request::create('https://example.com/about', 'GET', [], [], [], ['REMOTE_ADDR' => '9.9.9.9']);
    app(TrackVisit::class)->terminate($request, makeOkResponse());

    Queue::assertPushed(TrackVisitJob::class);
});

// --- Middleware: user_id ignore ---

it('does not track a request from an ignored user', function () {
    VisitorIgnore::create(['type' => 'user_id', 'value' => '42']);
    Cache::flush();

    Auth::shouldReceive('check')->andReturn(true);
    Auth::shouldReceive('id')->andReturn(42);

    $request = Request::create('https://example.com/about', 'GET');
    app(TrackVisit::class)->terminate($request, makeOkResponse());

    Queue::assertNotPushed(TrackVisitJob::class);
});

// --- Model: delete existing visits on creation ---

it('deletes existing visits by IP when an IP ignore entry is created', function () {
    Visit::factory()->count(3)->create(['ip_address' => '1.2.3.4']);
    Visit::factory()->count(2)->create(['ip_address' => '5.6.7.8']);

    VisitorIgnore::create(['type' => 'ip', 'value' => '1.2.3.4']);

    expect(Visit::where('ip_address', '1.2.3.4')->count())->toBe(0)
        ->and(Visit::where('ip_address', '5.6.7.8')->count())->toBe(2);
});

it('deletes existing visits by user_id when a user_id ignore entry is created', function () {
    Visit::factory()->count(3)->create(['user_id' => 7]);
    Visit::factory()->count(2)->create(['user_id' => 8]);

    VisitorIgnore::create(['type' => 'user_id', 'value' => '7']);

    expect(Visit::where('user_id', 7)->count())->toBe(0)
        ->and(Visit::where('user_id', 8)->count())->toBe(2);
});

// --- Cache invalidation ---

it('flushes the ignore list cache when an entry is created', function () {
    $key = 'visitor.ignore_list.'.LaravelVisitor::resolveConnection();
    Cache::put($key, ['ip' => [['value' => 'old.ip', 'is_blocked' => false]]], now()->addMinutes(5));

    VisitorIgnore::create(['type' => 'ip', 'value' => '1.2.3.4']);

    expect(Cache::has($key))->toBeFalse();
});

it('flushes the ignore list cache when an entry is deleted', function () {
    $key = 'visitor.ignore_list.'.LaravelVisitor::resolveConnection();
    $ignore = VisitorIgnore::create(['type' => 'ip', 'value' => '1.2.3.4']);
    Cache::put($key, ['ip' => [['value' => '1.2.3.4', 'is_blocked' => false]]], now()->addMinutes(5));

    $ignore->delete();

    expect(Cache::has($key))->toBeFalse();
});

it('flushes the ignore list cache when an entry is updated', function () {
    $key = 'visitor.ignore_list.'.LaravelVisitor::resolveConnection();
    $ignore = VisitorIgnore::create(['type' => 'ip', 'value' => '1.2.3.4']);
    Cache::put($key, ['ip' => [['value' => '1.2.3.4', 'is_blocked' => false]]], now()->addMinutes(5));

    $ignore->update(['value' => '5.6.7.8']);

    expect(Cache::has($key))->toBeFalse();
});

// --- Middleware: user_agent ignore ---

it('does not track a request from an ignored user agent', function () {
    VisitorIgnore::create(['type' => 'user_agent', 'value' => '*Googlebot*']);
    Cache::flush();

    $request = Request::create('https://example.com/about', 'GET');
    $request->headers->set('User-Agent', 'Googlebot/2.1 (+http://www.google.com/bot.html)');
    app(TrackVisit::class)->terminate($request, makeOkResponse());

    Queue::assertNotPushed(TrackVisitJob::class);
});

it('still tracks a request from a non-ignored user agent', function () {
    VisitorIgnore::create(['type' => 'user_agent', 'value' => '*Googlebot*']);
    Cache::flush();

    $request = Request::create('https://example.com/about', 'GET');
    $request->headers->set('User-Agent', 'Mozilla/5.0 (compatible; not-a-bot)');
    app(TrackVisit::class)->terminate($request, makeOkResponse());

    Queue::assertPushed(TrackVisitJob::class);
});

// --- Middleware: is_blocked ---

it('returns 403 when a request IP is blocked', function () {
    VisitorIgnore::create(['type' => 'ip', 'value' => '1.2.3.4', 'is_blocked' => true]);
    Cache::flush();

    $request = Request::create('https://example.com/about', 'GET', [], [], [], ['REMOTE_ADDR' => '1.2.3.4']);

    $response = app(TrackVisit::class)->handle($request, fn () => new Response('OK', 200));

    expect($response->getStatusCode())->toBe(403);
});

it('does not block a request from a non-blocked ignored IP', function () {
    VisitorIgnore::create(['type' => 'ip', 'value' => '1.2.3.4', 'is_blocked' => false]);
    Cache::flush();

    $request = Request::create('https://example.com/about', 'GET', [], [], [], ['REMOTE_ADDR' => '1.2.3.4']);

    $response = app(TrackVisit::class)->handle($request, fn () => new Response('OK', 200));

    expect($response->getStatusCode())->toBe(200);
});

it('returns 403 when a request user agent is blocked', function () {
    VisitorIgnore::create(['type' => 'user_agent', 'value' => '*Googlebot*', 'is_blocked' => true]);
    Cache::flush();

    $request = Request::create('https://example.com/about', 'GET');
    $request->headers->set('User-Agent', 'Googlebot/2.1 (+http://www.google.com/bot.html)');

    $response = app(TrackVisit::class)->handle($request, fn () => new Response('OK', 200));

    expect($response->getStatusCode())->toBe(403);
});
