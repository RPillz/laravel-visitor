<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use RPillz\LaravelVisitor\Http\Middleware\TrackVisit;
use RPillz\LaravelVisitor\Jobs\TrackVisitJob;
use RPillz\LaravelVisitor\Models\Visit;
use RPillz\LaravelVisitor\Models\VisitorIgnore;
use RPillz\LaravelVisitor\Support\VerifiedCrawlerResolver;
use Symfony\Component\HttpFoundation\Response;

beforeEach(function () {
    config([
        'visitor.probe_paths' => ['wp-admin*', '.env*'],
        'visitor.probe_block_duration' => null,
        'visitor.store_ip' => true,
        'visitor.verified_crawlers.enabled' => true,
        'visitor.verified_crawlers.cache_ttl' => 1440,
    ]);
    Cache::flush();
});

function fakeVerified(bool $result): void
{
    app()->instance(VerifiedCrawlerResolver::class, new class($result) extends VerifiedCrawlerResolver {
        public function __construct(private bool $verified) {}

        public function isVerified(Request $request): bool
        {
            return $this->verified;
        }
    });
}

// --- Probe path bypass ---

it('does not block a verified crawler hitting a probe path', function () {
    fakeVerified(true);

    $request = Request::create('https://example.com/wp-admin', 'GET', [], [], [], ['REMOTE_ADDR' => '66.249.66.1']);

    $response = app(TrackVisit::class)->handle($request, fn () => new Response('OK', 200));

    expect($response->getStatusCode())->toBe(200);
});

it('does not auto-block a verified crawler IP when it hits a probe path', function () {
    fakeVerified(true);

    $request = Request::create('https://example.com/wp-admin', 'GET', [], [], [], ['REMOTE_ADDR' => '66.249.66.1']);
    app(TrackVisit::class)->handle($request, fn () => new Response('OK', 200));

    expect(VisitorIgnore::where('type', 'ip')->where('value', '66.249.66.1')->exists())->toBeFalse();
});

it('still blocks an unverified bot hitting a probe path', function () {
    fakeVerified(false);

    $request = Request::create('https://example.com/wp-admin', 'GET', [], [], [], ['REMOTE_ADDR' => '1.2.3.4']);

    $response = app(TrackVisit::class)->handle($request, fn () => new Response('OK', 200));

    expect($response->getStatusCode())->toBe(404);
    expect(VisitorIgnore::where('type', 'ip')->where('value', '1.2.3.4')->exists())->toBeTrue();
});

// --- 404 threshold bypass ---

it('does not auto-block a verified crawler that hits the 404 threshold', function () {
    fakeVerified(true);
    config(['visitor.probe_404' => ['threshold' => 2, 'window' => 5]]);

    $request = Request::create('https://example.com/missing', 'GET', [], [], [], ['REMOTE_ADDR' => '66.249.66.1']);
    $middleware = app(TrackVisit::class);

    $middleware->terminate($request, new Response('Not Found', 404));
    $middleware->terminate($request, new Response('Not Found', 404));
    $middleware->terminate($request, new Response('Not Found', 404));

    expect(VisitorIgnore::where('type', 'ip')->where('value', '66.249.66.1')->exists())->toBeFalse();
});

it('still auto-blocks an unverified IP that hits the 404 threshold', function () {
    fakeVerified(false);
    config(['visitor.probe_404' => ['threshold' => 2, 'window' => 5]]);

    $request = Request::create('https://example.com/missing', 'GET', [], [], [], ['REMOTE_ADDR' => '1.2.3.4']);
    $middleware = app(TrackVisit::class);

    $middleware->terminate($request, new Response('Not Found', 404));
    $middleware->terminate($request, new Response('Not Found', 404));
    $middleware->terminate($request, new Response('Not Found', 404));

    expect(VisitorIgnore::where('type', 'ip')->where('value', '1.2.3.4')->exists())->toBeTrue();
});

// --- is_verified persisted on Visit ---

it('stores is_verified true on the visit when isVerified is true', function () {
    TrackVisitJob::dispatchSync(
        dbConnection: config('visitor.connection', 'visitor'),
        url: 'https://example.com/about',
        path: '/about',
        query: null,
        referrer: null,
        ipAddress: null,
        userAgent: 'Googlebot/2.1 (+http://www.google.com/bot.html)',
        sessionId: null,
        isUser: false,
        userId: null,
        isVerified: true,
    );

    expect(Visit::first()->is_verified)->toBeTrue();
});

it('stores is_verified false on the visit by default', function () {
    TrackVisitJob::dispatchSync(
        dbConnection: config('visitor.connection', 'visitor'),
        url: 'https://example.com/about',
        path: '/about',
        query: null,
        referrer: null,
        ipAddress: null,
        userAgent: 'Googlebot/2.1 (+http://www.google.com/bot.html)',
        sessionId: null,
        isUser: false,
        userId: null,
    );

    expect(Visit::first()->is_verified)->toBeFalse();
});

// --- VerifiedCrawlerResolver caching ---

it('caches the rDNS result per IP', function () {
    $callCount = 0;

    $resolver = new class($callCount) extends VerifiedCrawlerResolver {
        public function __construct(public int &$callCount) {}

        protected function verify(string $ip): bool
        {
            $this->callCount++;

            return false;
        }
    };

    app()->instance(VerifiedCrawlerResolver::class, $resolver);

    $request = Request::create('https://example.com/', 'GET', [], [], [], ['REMOTE_ADDR' => '1.2.3.4']);

    $resolver->isVerified($request);
    $resolver->isVerified($request);
    $resolver->isVerified($request);

    expect($callCount)->toBe(1);
});

it('returns false immediately when verified_crawlers is disabled', function () {
    config(['visitor.verified_crawlers.enabled' => false]);

    $resolver = new VerifiedCrawlerResolver;
    $request = Request::create('https://example.com/', 'GET', [], [], [], ['REMOTE_ADDR' => '66.249.66.1']);

    expect($resolver->isVerified($request))->toBeFalse();
});
