<?php

namespace RPillz\LaravelVisitor\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use RPillz\LaravelVisitor\Models\Visit;

class OverviewStatsWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $human = Visit::whereNull('bot_name');

        $totalVisits = (clone $human)->count();
        $uniqueVisitors = (clone $human)->whereNotNull('session_id')->distinct('session_id')->count('session_id');
        $todayVisits = (clone $human)->whereDate('created_at', today())->count();
        $last7Days = (clone $human)->where('created_at', '>=', now()->subDays(7))->count();

        return [
            Stat::make('Total Visits', number_format($totalVisits))
                ->icon('heroicon-o-eye'),

            Stat::make('Unique Visitors', number_format($uniqueVisitors))
                ->description('By session ID')
                ->icon('heroicon-o-users'),

            Stat::make('Today', number_format($todayVisits))
                ->description('Last 7 days: '.number_format($last7Days))
                ->icon('heroicon-o-calendar-days'),
        ];
    }
}
