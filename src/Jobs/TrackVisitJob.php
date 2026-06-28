<?php

namespace RPillz\LaravelVisitor\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RPillz\LaravelVisitor\Models\Visit;
use RPillz\LaravelVisitor\Support\AgentResolver;
use RPillz\LaravelVisitor\Support\GeoResolver;

class TrackVisitJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $dbConnection,
        public readonly string $url,
        public readonly string $path,
        public readonly ?string $query,
        public readonly ?string $referrer,
        public readonly ?string $ipAddress,
        public readonly ?string $userAgent,
        public readonly ?string $sessionId,
        public readonly bool $isUser,
        public readonly ?int $userId,
        public readonly bool $isBlocked = false,
        public readonly bool $isVerified = false,
        public readonly ?string $headerFingerprint = null,
        public readonly bool $looksLikeBrowser = true,
    ) {}

    public function handle(GeoResolver $geoResolver, AgentResolver $agentResolver): void
    {
        $geo = $this->ipAddress
            ? $geoResolver->resolve($this->ipAddress)
            : ['country' => null, 'city' => null];

        $agent = $this->userAgent
            ? $agentResolver->resolve($this->userAgent)
            : ['device_type' => null, 'browser' => null, 'os' => null, 'is_robot' => false, 'bot_name' => null];

        if (! $this->looksLikeBrowser && ! $agent['bot_name']) {
            $agent['bot_name'] = 'Unidentified Bot';
        }

        $referrerDomain = null;
        if ($this->referrer) {
            $parsed = parse_url($this->referrer);
            $referrerDomain = $parsed['host'] ?? null;
        }

        Visit::on($this->dbConnection)->create([
            'url' => $this->url,
            'path' => $this->path,
            'query' => $this->query,
            'referrer' => $this->referrer,
            'referrer_domain' => $referrerDomain,
            'ip_address' => $this->ipAddress,
            'country' => $geo['country'],
            'city' => $geo['city'],
            'device_type' => $agent['device_type'],
            'browser' => $agent['browser'],
            'os' => $agent['os'],
            'user_agent' => $this->userAgent,
            'header_fingerprint' => $this->headerFingerprint,
            'bot_name' => $agent['bot_name'],
            'is_blocked' => $this->isBlocked,
            'is_verified' => $this->isVerified,
            'is_user' => $this->isUser,
            'user_id' => $this->userId,
            'session_id' => $this->sessionId,
        ]);
    }
}
