<?php

namespace RPillz\LaravelVisitor\Support;

use GeoIp2\Database\Reader;

class GeoResolver
{
    protected ?Reader $reader = null;

    public function resolve(string $ip): array
    {
        if (! config('visitor.geoip.enabled', true)) {
            return ['country' => null, 'city' => null];
        }

        try {
            $record = $this->getReader()->city($ip);

            return [
                'country' => $record->country->isoCode,
                'city' => $record->city->name,
            ];
        } catch (\Exception) {
            return ['country' => null, 'city' => null];
        }
    }

    protected function getReader(): Reader
    {
        if (! $this->reader) {
            $this->reader = new Reader(config('visitor.geoip.database'));
        }

        return $this->reader;
    }

    public function __destruct()
    {
        $this->reader?->close();
    }
}
