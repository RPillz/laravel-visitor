<?php

use RPillz\LaravelVisitor\Models\Visit;

beforeEach(function () {
    config(['visitor.deduplication.enabled' => false]);
});

it('erases visits by user ID', function () {
    Visit::factory()->count(3)->create(['user_id' => 42]);
    Visit::factory()->count(2)->create(['user_id' => 99]);

    $this->artisan('visitor:forget', ['userId' => 42, '--force' => true])
        ->assertSuccessful();

    expect(Visit::where('user_id', 42)->count())->toBe(0)
        ->and(Visit::where('user_id', 99)->count())->toBe(2);
});

it('erases visits by session ID', function () {
    Visit::factory()->count(3)->create(['session_id' => 'session-abc']);
    Visit::factory()->count(2)->create(['session_id' => 'session-xyz']);

    $this->artisan('visitor:forget', ['--session' => 'session-abc', '--force' => true])
        ->assertSuccessful();

    expect(Visit::where('session_id', 'session-abc')->count())->toBe(0)
        ->and(Visit::where('session_id', 'session-xyz')->count())->toBe(2);
});

it('reports when no records are found for user ID', function () {
    $this->artisan('visitor:forget', ['userId' => 999, '--force' => true])
        ->expectsOutput('No visit records found for user ID 999.')
        ->assertSuccessful();
});

it('reports when no records are found for session ID', function () {
    $this->artisan('visitor:forget', ['--session' => 'ghost-session', '--force' => true])
        ->expectsOutput('No visit records found for session ID ghost-session.')
        ->assertSuccessful();
});

it('erases visits by IP address', function () {
    Visit::factory()->count(3)->create(['ip_address' => '1.2.3.4']);
    Visit::factory()->count(2)->create(['ip_address' => '5.6.7.8']);

    $this->artisan('visitor:forget', ['--ip' => '1.2.3.4', '--force' => true])
        ->assertSuccessful();

    expect(Visit::where('ip_address', '1.2.3.4')->count())->toBe(0)
        ->and(Visit::where('ip_address', '5.6.7.8')->count())->toBe(2);
});

it('reports when no records are found for IP address', function () {
    $this->artisan('visitor:forget', ['--ip' => '9.9.9.9', '--force' => true])
        ->expectsOutput('No visit records found for IP address 9.9.9.9.')
        ->assertSuccessful();
});

it('fails when neither userId nor --session nor --ip is provided', function () {
    $this->artisan('visitor:forget', ['--force' => true])
        ->assertFailed();
});
