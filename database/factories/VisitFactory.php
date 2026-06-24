<?php

namespace RPillz\LaravelVisitor\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use RPillz\LaravelVisitor\Models\Visit;

class VisitFactory extends Factory
{
    protected $model = Visit::class;

    public function definition(): array
    {
        $paths = ['/about', '/contact', '/blog', '/products', '/pricing', '/'];
        $browsers = ['Chrome', 'Firefox', 'Safari', 'Edge'];
        $oses = ['Windows', 'macOS', 'Linux', 'iOS', 'Android'];
        $devices = ['desktop', 'mobile', 'tablet'];
        $countries = ['US', 'GB', 'CA', 'AU', 'DE', 'FR'];

        $path = $this->faker->randomElement($paths);
        $referrerDomain = $this->faker->optional()->domainName();

        return [
            'url' => 'https://example.com'.$path,
            'path' => $path,
            'query' => null,
            'referrer' => $referrerDomain ? 'https://'.$referrerDomain : null,
            'referrer_domain' => $referrerDomain,
            'ip_address' => $this->faker->ipv4(),
            'country' => $this->faker->randomElement($countries),
            'city' => $this->faker->city(),
            'device_type' => $this->faker->randomElement($devices),
            'browser' => $this->faker->randomElement($browsers),
            'os' => $this->faker->randomElement($oses),
            'user_agent' => $this->faker->userAgent(),
            'bot_name' => null,
            'is_user' => false,
            'user_id' => null,
            'session_id' => $this->faker->uuid(),
            'created_at' => $this->faker->dateTimeBetween('-90 days', 'now'),
        ];
    }
}
