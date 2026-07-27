<?php

use Illuminate\Support\Str;
use RPillz\LaravelVisitor\Jobs\TrackVisitJob;
use RPillz\LaravelVisitor\Models\Visit;
use RPillz\LaravelVisitor\Support\AgentResolver;
use RPillz\LaravelVisitor\Support\GeoResolver;

it('truncates visit data to the corresponding database column lengths', function () {
    $geoResolver = Mockery::mock(GeoResolver::class);
    $geoResolver->shouldReceive('resolve')->once()->andReturn([
        'country' => 'CAN',
        'city' => str_repeat("\u{00E9}", 300),
    ]);
    app()->instance(GeoResolver::class, $geoResolver);

    $agentResolver = Mockery::mock(AgentResolver::class);
    $agentResolver->shouldReceive('resolve')->once()->andReturn([
        'device_type' => str_repeat('d', 30),
        'browser' => str_repeat('b', 70),
        'os' => str_repeat('o', 70),
        'is_robot' => true,
        'bot_name' => str_repeat('n', 110),
    ]);
    app()->instance(AgentResolver::class, $agentResolver);

    TrackVisitJob::dispatchSync(
        dbConnection: config('visitor.connection', 'visitor'),
        url: 'https://example.com/'.str_repeat('u', 300),
        path: '/'.str_repeat('p', 300),
        query: str_repeat('q', 300),
        referrer: 'https://'.str_repeat('r', 300).'.example.com/path',
        ipAddress: str_repeat('i', 50),
        userAgent: 'test-agent',
        sessionId: str_repeat('s', 110),
        isUser: false,
        userId: null,
        headerFingerprint: str_repeat('f', 70),
    );

    $visit = Visit::firstOrFail();

    expect(Str::length($visit->url))->toBe(255)
        ->and(Str::length($visit->path))->toBe(255)
        ->and(Str::length($visit->query))->toBe(255)
        ->and(Str::length($visit->referrer))->toBe(255)
        ->and(Str::length($visit->referrer_domain))->toBe(255)
        ->and(Str::length($visit->ip_address))->toBe(45)
        ->and(Str::length($visit->country))->toBe(2)
        ->and(Str::length($visit->city))->toBe(255)
        ->and(Str::length($visit->device_type))->toBe(20)
        ->and(Str::length($visit->browser))->toBe(60)
        ->and(Str::length($visit->os))->toBe(60)
        ->and(Str::length($visit->header_fingerprint))->toBe(64)
        ->and(Str::length($visit->bot_name))->toBe(100)
        ->and(Str::length($visit->session_id))->toBe(100)
        ->and(mb_check_encoding($visit->city, 'UTF-8'))->toBeTrue();
});

it('preserves null values while truncating visit data', function () {
    TrackVisitJob::dispatchSync(
        dbConnection: config('visitor.connection', 'visitor'),
        url: 'https://example.com/'.str_repeat('u', 300),
        path: '/',
        query: null,
        referrer: null,
        ipAddress: null,
        userAgent: null,
        sessionId: null,
        isUser: false,
        userId: null,
    );

    $visit = Visit::firstOrFail();

    expect($visit->query)->toBeNull()
        ->and($visit->referrer)->toBeNull()
        ->and($visit->referrer_domain)->toBeNull()
        ->and($visit->ip_address)->toBeNull()
        ->and($visit->user_agent)->toBeNull()
        ->and($visit->session_id)->toBeNull();
});
