<?php

use RPillz\LaravelVisitor\Models\Visit;
use RPillz\LaravelVisitor\Models\VisitorIgnore;

beforeEach(function () {
    config(['visitor.track_bots' => true]);
});

function botVisit(string $botName, ?string $fingerprint = null): Visit
{
    return Visit::create([
        'url' => 'https://example.com/',
        'path' => '/',
        'bot_name' => $botName,
        'header_fingerprint' => $fingerprint,
        'is_blocked' => false,
        'is_verified' => false,
    ]);
}

function blockRecord(Visit $record): void
{
    if (filled($record->header_fingerprint)) {
        VisitorIgnore::updateOrCreate(
            ['type' => 'header_fingerprint', 'value' => $record->header_fingerprint],
            ['is_blocked' => true, 'is_automatic' => false],
        );
    } else {
        VisitorIgnore::updateOrCreate(
            ['type' => 'user_agent', 'value' => '*'.$record->bot_name.'*'],
            ['is_blocked' => true, 'is_automatic' => false],
        );
    }
}

// --- Query: one real Visit row per bot+fingerprint combo ---

it('query returns one row per bot+fingerprint combination', function () {
    botVisit('Googlebot', 'fp_abc');
    botVisit('Googlebot', 'fp_abc');   // duplicate — same bot+fingerprint
    botVisit('Googlebot', 'fp_xyz');   // different fingerprint
    botVisit('Bingbot', null);

    $latestPerBot = Visit::withoutGlobalScope('exclude_blocked')
        ->selectRaw('MAX(id)')
        ->whereNotNull('bot_name')
        ->groupBy('bot_name', 'header_fingerprint');

    $results = Visit::withoutGlobalScope('exclude_blocked')
        ->whereIn('id', $latestPerBot)
        ->get();

    expect($results)->toHaveCount(3);
});

it('query rows have real IDs resolvable via Visit::find', function () {
    $created = botVisit('Googlebot', 'fp_abc');

    $latestPerBot = Visit::withoutGlobalScope('exclude_blocked')
        ->selectRaw('MAX(id)')
        ->whereNotNull('bot_name')
        ->groupBy('bot_name', 'header_fingerprint');

    $row = Visit::withoutGlobalScope('exclude_blocked')
        ->whereIn('id', $latestPerBot)
        ->first();

    expect($row)->not->toBeNull()
        ->and($row->id)->toBe($created->id)
        ->and(Visit::withoutGlobalScope('exclude_blocked')->find($row->id))->not->toBeNull();
});

it('visit_count reflects the total visits for that bot+fingerprint', function () {
    botVisit('Googlebot', 'fp_abc');
    botVisit('Googlebot', 'fp_abc');
    botVisit('Googlebot', 'fp_abc');

    $latestPerBot = Visit::withoutGlobalScope('exclude_blocked')
        ->selectRaw('MAX(id)')
        ->whereNotNull('bot_name')
        ->groupBy('bot_name', 'header_fingerprint');

    $row = Visit::withoutGlobalScope('exclude_blocked')
        ->selectRaw('*, (SELECT COUNT(*) FROM visits v2 WHERE v2.bot_name = visits.bot_name AND (v2.header_fingerprint = visits.header_fingerprint OR (v2.header_fingerprint IS NULL AND visits.header_fingerprint IS NULL))) as visit_count')
        ->whereIn('id', $latestPerBot)
        ->first();

    expect((int) $row->visit_count)->toBe(3);
});

// --- Block logic ---

it('block action creates a header_fingerprint ignore entry when fingerprint is present', function () {
    $visit = botVisit('Googlebot', 'fp_abc123');

    blockRecord($visit);

    expect(VisitorIgnore::where('type', 'header_fingerprint')->where('value', 'fp_abc123')->where('is_blocked', true)->exists())->toBeTrue();
});

it('block action creates a user_agent ignore entry when fingerprint is null', function () {
    $visit = botVisit('Bingbot');

    blockRecord($visit);

    expect(VisitorIgnore::where('type', 'user_agent')->where('value', '*Bingbot*')->where('is_blocked', true)->exists())->toBeTrue();
});

it('block action is idempotent when called twice for the same bot', function () {
    $visit = botVisit('Googlebot', 'fp_abc123');

    blockRecord($visit);
    blockRecord($visit);

    expect(VisitorIgnore::where('type', 'header_fingerprint')->where('value', 'fp_abc123')->count())->toBe(1);
});

it('blocking by fingerprint does not create an entry for a different fingerprint of the same bot', function () {
    $visitA = botVisit('Googlebot', 'fp_aaa');
    $visitB = botVisit('Googlebot', 'fp_bbb');

    blockRecord($visitA);

    expect(VisitorIgnore::where('value', 'fp_aaa')->exists())->toBeTrue();
    expect(VisitorIgnore::where('value', 'fp_bbb')->exists())->toBeFalse();
});
