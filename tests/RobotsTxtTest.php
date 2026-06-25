<?php

use Illuminate\Support\Facades\Route;

beforeEach(function () {
    config([
        'visitor.robots_txt.enabled' => false,
        'visitor.robots_txt.disallow' => ['ClaudeBot', 'Amazonbot', 'GPTBot'],
    ]);
});

// --- Route registration ---

it('always registers the visitor.robots-txt route', function () {
    expect(Route::has('visitor.robots-txt'))->toBeTrue();
});

it('returns 404 when robots_txt is disabled', function () {
    config(['visitor.robots_txt.enabled' => false]);

    $this->get('robots.txt')->assertStatus(404);
});

// --- Response format ---

it('serves a text/plain response when enabled', function () {
    config(['visitor.robots_txt.enabled' => true]);

    $this->get('robots.txt')
        ->assertStatus(200)
        ->assertHeader('Content-Type', 'text/plain; charset=utf-8');
});

it('includes a Disallow entry for each configured agent', function () {
    config(['visitor.robots_txt.enabled' => true]);

    $body = $this->get('robots.txt')->getContent();

    expect($body)->toContain('User-agent: ClaudeBot')
        ->and($body)->toContain('User-agent: Amazonbot')
        ->and($body)->toContain('User-agent: GPTBot');

    foreach (['ClaudeBot', 'Amazonbot', 'GPTBot'] as $agent) {
        expect($body)->toContain("User-agent: {$agent}\nDisallow: /");
    }
});

it('produces an empty body when disallow list is empty', function () {
    config([
        'visitor.robots_txt.enabled' => true,
        'visitor.robots_txt.disallow' => [],
    ]);

    expect(trim($this->get('robots.txt')->getContent()))->toBe('');
});
