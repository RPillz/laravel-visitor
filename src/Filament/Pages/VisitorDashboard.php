<?php

namespace RPillz\LaravelVisitor\Filament\Pages;

use Filament\Pages\Page;
use RPillz\LaravelVisitor\Filament\Widgets\BotStatsWidget;
use RPillz\LaravelVisitor\Filament\Widgets\BouncePagesWidget;
use RPillz\LaravelVisitor\Filament\Widgets\DevicesWidget;
use RPillz\LaravelVisitor\Filament\Widgets\LandingPagesWidget;
use RPillz\LaravelVisitor\Filament\Widgets\OverviewStatsWidget;
use RPillz\LaravelVisitor\Filament\Widgets\ReferrersWidget;
use RPillz\LaravelVisitor\Filament\Widgets\TopBotsWidget;
use RPillz\LaravelVisitor\Filament\Widgets\TopPagesWidget;
use RPillz\LaravelVisitor\Filament\Widgets\VisitsChartWidget;

class VisitorDashboard extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationLabel = 'Analytics';

    protected static ?string $slug = 'analytics';

    protected static ?int $navigationSort = 99;

    public function getTitle(): string
    {
        return 'Analytics';
    }

    protected function getHeaderWidgets(): array
    {
        return [
            OverviewStatsWidget::class,
            BotStatsWidget::class,
            VisitsChartWidget::class,
            TopPagesWidget::class,
            LandingPagesWidget::class,
            BouncePagesWidget::class,
            ReferrersWidget::class,
            DevicesWidget::class,
            TopBotsWidget::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }
}
