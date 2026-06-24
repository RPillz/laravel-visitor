<?php

namespace RPillz\LaravelVisitor\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use RPillz\LaravelVisitor\Models\Visit;

class BlockStatsWidget extends StatsOverviewWidget
{
    public static function canView(): bool
    {
        return (bool) config('visitor.log_blocks', false);
    }

    protected function getStats(): array
    {
        $blocked = Visit::withoutGlobalScope('exclude_blocked')->where('is_blocked', true);

        $total = (clone $blocked)->count();
        $today = (clone $blocked)->whereDate('created_at', today())->count();
        $last7Days = (clone $blocked)->where('created_at', '>=', now()->subDays(7))->count();
        $uniqueIps = (clone $blocked)->whereNotNull('ip_address')->distinct('ip_address')->count('ip_address');

        return [
            Stat::make('Blocked Attempts', number_format($total))
                ->icon('heroicon-o-shield-exclamation'),

            Stat::make('Blocked Today', number_format($today))
                ->description('Last 7 days: '.number_format($last7Days))
                ->icon('heroicon-o-calendar-days'),

            Stat::make('Unique Blocked IPs', number_format($uniqueIps))
                ->icon('heroicon-o-no-symbol'),
        ];
    }
}
