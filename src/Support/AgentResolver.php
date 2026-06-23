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
        ];
    }
}
