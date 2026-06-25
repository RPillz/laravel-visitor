<?php

namespace RPillz\LaravelVisitor\Support;

use Jenssegers\Agent\Agent;

class AgentResolver
{
    public function isBot(string $userAgent): bool
    {
        $agent = new Agent;
        $agent->setUserAgent($userAgent);

        return $agent->isRobot();
    }

    // Bots whose UA strings are not reliably named by CrawlerDetect.
    protected static array $uaPatterns = [
        'meta-externalagent'  => 'Meta-ExternalAgent',
        'meta-externalads'    => 'Meta-ExternalAds',
        'meta-externalfetcher' => 'Meta-ExternalFetcher',
        'meta-webindexer'     => 'Meta-WebIndexer',
    ];

    public function botName(string $userAgent): ?string
    {
        $lower = strtolower($userAgent);
        foreach (static::$uaPatterns as $needle => $name) {
            if (str_contains($lower, $needle)) {
                return $name;
            }
        }

        $agent = new Agent;
        $agent->setUserAgent($userAgent);

        if (! $agent->isRobot()) {
            return null;
        }

        return $agent->robot() ?: null;
    }

    public function resolve(string $userAgent): array
    {
        $agent = new Agent;
        $agent->setUserAgent($userAgent);

        return [
            'device_type' => match (true) {
                $agent->isTablet() => 'tablet',
                $agent->isMobile() => 'mobile',
                default => 'desktop',
            },
            'browser' => $agent->browser() ?: null,
            'os' => $agent->platform() ?: null,
            'is_robot' => $agent->isRobot(),
            'bot_name' => $agent->isRobot() ? ($agent->robot() ?: null) : null,
        ];
    }
}
