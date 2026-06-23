<?php

namespace RPillz\LaravelVisitor\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use RPillz\LaravelVisitor\Filament\Pages\VisitorDashboard;
use RPillz\LaravelVisitor\Filament\Resources\VisitorIgnoreResource;

class VisitorPlugin implements Plugin
{
    public function getId(): string
    {
        return 'laravel-visitor';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->pages([
                VisitorDashboard::class,
            ])
            ->resources([
                VisitorIgnoreResource::class,
            ]);
    }

    public function boot(Panel $panel): void {}

    public static function make(): static
    {
        return app(static::class);
    }
}
