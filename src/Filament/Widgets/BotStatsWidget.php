<?php

namespace RPillz\LaravelVisitor\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use RPillz\LaravelVisitor\Models\Visit;

class BotStatsWidget extends StatsOverviewWidget
{
    public static function canView(): bool
    {
        return (bool) config('visitor.track_bots', true);
    }

    protected function getStats(): array
    {
        $bots = Visit::whereNotNull('bot_name');

        $totalVisits = (clone $bots)->count();
        $todayVisits = (clone $bots)->whereDate('created_at', today())->count();
        $last7Days = (clone $bots)->where('created_at', '>=', now()->subDays(7))->count();

        return [
            Stat::make('Bot Visits', number_format($totalVisits))
                ->icon('heroicon-o-cpu-chip'),

            Stat::make('Bots Today', number_format($todayVisits))
                ->description('Last 7 days: '.number_format($last7Days))
                ->icon('heroicon-o-calendar-days'),
        ];
    }
}
