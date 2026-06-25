<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RPillz\LaravelVisitor\Http\Middleware\TrackVisit;
use RPillz\LaravelVisitor\Support\VerifiedCrawlerResolver;
use Symfony\Component\HttpFoundation\Response;

const SEMRUSH_UA = 'Mozilla/5.0 (compatible; SemrushBot/7~bl; +http://www.semrush.com/bot.html)';
const CLAUDEBOT_UA = 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; ClaudeBot/1.0; +claudebot@anthropic.com)';
const GOOGLEBOT_UA_BLOCK = 'Googlebot/2.1 (+http://www.google.com/bot.html)';
const PHARE_UA = 'Mozilla/5.0 (compatible; Phare/1.0; +https://phare.io/products/uptime)';

beforeEach(function () {
    config([
        'visitor.block_probes' => false,
        'visitor.rate_limit.enabled' => false,
        'visitor.block_verified_bots' => [],
        'visitor.block_unverified_bots' => false,
        'visitor.verified_crawlers.enabled' => true,
        'visitor.verified_crawlers.ip_lists' => [],
    ]);
    Cache::flush();
    Http::preventStrayRequests();
});

// --- block_verified_bots ---

it('blocks a request whose bot name is in block_verified_bots', function () {
    config(['visitor.block_verified_bots' => ['Semrush']]);

    $request = Request::create('https://example.com/about', 'GET', [], [], [], [
        'REMOTE_ADDR' => '1.2.3.4',
        'HTTP_USER_AGENT' => SEMRUSH_UA,
    ]);

    $response = app(TrackVisit::class)->handle($request, fn () => new Response('OK', 200));

    expect($response->getStatusCode())->toBe(403);
});

it('does not block a request whose bot name is not in block_verified_bots', function () {
    config(['visitor.block_verified_bots' => ['Ahrefs']]);

    $request = Request::create('https://example.com/about', 'GET', [], [], [], [
        'REMOTE_ADDR' => '1.2.3.4',
        'HTTP_USER_AGENT' => SEMRUSH_UA,
    ]);

    $response = app(TrackVisit::class)->handle($request, fn () => new Response('OK', 200));

    expect($response->getStatusCode())->toBe(200);
});

it('does not block a human browser request when block_verified_bots is set', function () {
    config(['visitor.block_verified_bots' => ['Semrush', 'Ahrefs']]);

    $request = Request::create('https://example.com/about', 'GET', [], [], [], [
        'REMOTE_ADDR' => '1.2.3.4',
        'HTTP_USER_AGENT' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    ]);

    $response = app(TrackVisit::class)->handle($request, fn () => new Response('OK', 200));

    expect($response->getStatusCode())->toBe(200);
});

it('does not block when block_verified_bots is empty', function () {
    config(['visitor.block_verified_bots' => []]);

    $request = Request::create('https://example.com/about', 'GET', [], [], [], [
        'REMOTE_ADDR' => '1.2.3.4',
        'HTTP_USER_AGENT' => SEMRUSH_UA,
    ]);

    $response = app(TrackVisit::class)->handle($request, fn () => new Response('OK', 200));

    expect($response->getStatusCode())->toBe(200);
});

// --- block_unverified_bots ---

it('blocks an identified bot that fails verification when block_unverified_bots is true', function () {
    config(['visitor.block_unverified_bots' => true]);

    app()->instance(VerifiedCrawlerResolver::class, new class extends VerifiedCrawlerResolver {
        public function isVerified(Request $request): bool { return false; }
    });

    $request = Request::create('https://example.com/about', 'GET', [], [], [], [
        'REMOTE_ADDR' => '1.2.3.4',
        'HTTP_USER_AGENT' => CLAUDEBOT_UA,
    ]);

    $response = app(TrackVisit::class)->handle($request, fn () => new Response('OK', 200));

    expect($response->getStatusCode())->toBe(403);
});

it('allows a verified bot through when block_unverified_bots is true', function () {
    config(['visitor.block_unverified_bots' => true]);

    app()->instance(VerifiedCrawlerResolver::class, new class extends VerifiedCrawlerResolver {
        public function isVerified(Request $request): bool { return true; }
    });

    $request = Request::create('https://example.com/about', 'GET', [], [], [], [
        'REMOTE_ADDR' => '66.249.66.1',
        'HTTP_USER_AGENT' => GOOGLEBOT_UA_BLOCK,
    ]);

    $response = app(TrackVisit::class)->handle($request, fn () => new Response('OK', 200));

    expect($response->getStatusCode())->toBe(200);
});

it('does not block an unverified bot when block_unverified_bots is false', function () {
    config(['visitor.block_unverified_bots' => false]);

    app()->instance(VerifiedCrawlerResolver::class, new class extends VerifiedCrawlerResolver {
        public function isVerified(Request $request): bool { return false; }
    });

    $request = Request::create('https://example.com/about', 'GET', [], [], [], [
        'REMOTE_ADDR' => '1.2.3.4',
        'HTTP_USER_AGENT' => CLAUDEBOT_UA,
    ]);

    $response = app(TrackVisit::class)->handle($request, fn () => new Response('OK', 200));

    expect($response->getStatusCode())->toBe(200);
});

it('does not block a request with no bot name when block_unverified_bots is true', function () {
    config(['visitor.block_unverified_bots' => true]);

    app()->instance(VerifiedCrawlerResolver::class, new class extends VerifiedCrawlerResolver {
        public function isVerified(Request $request): bool { return false; }
    });

    // Generic browser UA — no bot name resolved.
    $request = Request::create('https://example.com/about', 'GET', [], [], [], [
        'REMOTE_ADDR' => '1.2.3.4',
        'HTTP_USER_AGENT' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
    ]);

    $response = app(TrackVisit::class)->handle($request, fn () => new Response('OK', 200));

    expect($response->getStatusCode())->toBe(200);
});

// --- block_verified_bots + block_unverified_bots together ---

it('block_verified_bots blocks even when verification passes', function () {
    config([
        'visitor.block_verified_bots' => ['Semrush'],
        'visitor.block_unverified_bots' => true,
    ]);

    // Even if verification passes, block_verified_bots wins.
    app()->instance(VerifiedCrawlerResolver::class, new class extends VerifiedCrawlerResolver {
        public function isVerified(Request $request): bool { return true; }
    });

    $request = Request::create('https://example.com/about', 'GET', [], [], [], [
        'REMOTE_ADDR' => '1.2.3.4',
        'HTTP_USER_AGENT' => SEMRUSH_UA,
    ]);

    $response = app(TrackVisit::class)->handle($request, fn () => new Response('OK', 200));

    expect($response->getStatusCode())->toBe(403);
});

// --- allow_agents ---

it('allows an agent matching allow_agents through block_unverified_bots', function () {
    config([
        'visitor.block_unverified_bots' => true,
        'visitor.allow_agents' => ['Phare'],
    ]);

    app()->instance(VerifiedCrawlerResolver::class, new class extends VerifiedCrawlerResolver {
        public function isVerified(Request $request): bool { return false; }
    });

    $request = Request::create('https://example.com/', 'GET', [], [], [], [
        'REMOTE_ADDR' => '1.2.3.4',
        'HTTP_USER_AGENT' => PHARE_UA,
    ]);

    $response = app(TrackVisit::class)->handle($request, fn () => new Response('OK', 200));

    expect($response->getStatusCode())->toBe(200);
});

it('allows an agent matching allow_agents through block_verified_bots', function () {
    config([
        'visitor.block_verified_bots' => ['Semrush'],
        'visitor.allow_agents' => ['SemrushBot'],
    ]);

    $request = Request::create('https://example.com/', 'GET', [], [], [], [
        'REMOTE_ADDR' => '1.2.3.4',
        'HTTP_USER_AGENT' => SEMRUSH_UA,
    ]);

    $response = app(TrackVisit::class)->handle($request, fn () => new Response('OK', 200));

    expect($response->getStatusCode())->toBe(200);
});

it('still blocks agents not matching allow_agents when block_unverified_bots is true', function () {
    config([
        'visitor.block_unverified_bots' => true,
        'visitor.allow_agents' => ['Phare'],
    ]);

    app()->instance(VerifiedCrawlerResolver::class, new class extends VerifiedCrawlerResolver {
        public function isVerified(Request $request): bool { return false; }
    });

    $request = Request::create('https://example.com/', 'GET', [], [], [], [
        'REMOTE_ADDR' => '1.2.3.4',
        'HTTP_USER_AGENT' => CLAUDEBOT_UA,
    ]);

    $response = app(TrackVisit::class)->handle($request, fn () => new Response('OK', 200));

    expect($response->getStatusCode())->toBe(403);
});
