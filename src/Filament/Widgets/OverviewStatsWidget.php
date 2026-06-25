<?php

namespace RPillz\LaravelVisitor\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use RPillz\LaravelVisitor\Models\Visit;

class OverviewStatsWidget extends StatsOverviewWidget
{
    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $human = Visit::whereNull('bot_name');

        $totalVisits = (clone $human)->count();
        $uniqueVisitors = (clone $human)->whereNotNull('session_id')->distinct('session_id')->count('session_id');
        $todayVisits = (clone $human)->whereDate('created_at', today())->count();
        $last7Days = (clone $human)->where('created_at', '>=', now()->subDays(7))->count();

        $stats = [
            Stat::make('Total Visits', number_format($totalVisits))
                ->description('Unique visitors: '.number_format($uniqueVisitors))
                ->icon('heroicon-o-eye'),

            Stat::make('Last 7 Days', number_format($last7Days))
                ->description('Today: '.number_format($todayVisits))
                ->icon('heroicon-o-calendar-days'),
        ];

        if (config('visitor.track_bots', true)) {
            $bots = Visit::whereNotNull('bot_name');
            $totalBots = (clone $bots)->count();
            $verifiedBots = (clone $bots)->where('is_verified', true)->count();
            $todayBots = (clone $bots)->whereDate('created_at', today())->count();

            $stats[] = Stat::make('Bot Visits', number_format($totalBots))
                ->description('Today: '.number_format($todayBots).' · Verified: '.number_format($verifiedBots))
                ->icon('heroicon-o-cpu-chip');
        }

        if (config('visitor.log_blocks', false)) {
            $blocked = Visit::withoutGlobalScope('exclude_blocked')->where('is_blocked', true);
            $totalBlocked = (clone $blocked)->count();
            $todayBlocked = (clone $blocked)->whereDate('created_at', today())->count();

            $stats[] = Stat::make('Blocked', number_format($totalBlocked))
                ->description('Today: '.number_format($todayBlocked))
                ->icon('heroicon-o-shield-exclamation');
        }

        return $stats;
    }
}
