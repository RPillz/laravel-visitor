<?php

namespace RPillz\LaravelVisitor\Filament\Pages;

use Filament\Pages\Page;
use RPillz\LaravelVisitor\Filament\Widgets\BouncePagesWidget;
use RPillz\LaravelVisitor\Models\Visit;
use RPillz\LaravelVisitor\Filament\Widgets\BotVisitsChartWidget;
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

    protected static ?string $navigationLabel = 'Visitor Report';

    protected static ?string $slug = 'visitor-report';

    protected static ?int $navigationSort = 99;

    public function getTitle(): string
    {
        return 'Visitor Report';
    }

    public function getSubheading(): string|null
    {
        $oldest = Visit::withoutGlobalScope('exclude_blocked')->oldest()->value('created_at');

        return $oldest ? 'Visits since '.date('F j, Y', strtotime($oldest)) : null;
    }

    protected function getHeaderWidgets(): array
    {
        return [
            OverviewStatsWidget::class,
            VisitsChartWidget::class,
            BotVisitsChartWidget::class,
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
        return ['default' => 1, 'md' => 2];
    }
}
