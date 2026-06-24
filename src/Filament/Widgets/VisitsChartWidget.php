<?php

namespace RPillz\LaravelVisitor\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use RPillz\LaravelVisitor\Models\Visit;

class VisitsChartWidget extends ChartWidget
{
    protected ?string $heading = 'Visits Over Time';

    public ?string $filter = '30';

    protected function getFilters(): ?array
    {
        return [
            '7' => 'Last 7 days',
            '30' => 'Last 30 days',
            '90' => 'Last 90 days',
        ];
    }

    protected function getData(): array
    {
        $days = (int) ($this->filter ?? 30);

        $utcNow = now()->utc();
        $start = $utcNow->copy()->subDays($days - 1)->startOfDay();

        $groupedBy = fn (callable $scope) => Visit::query()
            ->where('created_at', '>=', $start)
            ->tap($scope)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('total', 'date');

        $trackBots = config('visitor.track_bots', true);

        $userVisits = $groupedBy(fn ($q) => $q->whereNull('bot_name')->where('is_user', true));
        $guestVisits = $groupedBy(fn ($q) => $q->whereNull('bot_name')->where('is_user', false));
        $botVisits = $trackBots ? $groupedBy(fn ($q) => $q->whereNotNull('bot_name')) : collect();

        $labels = [];
        $userData = [];
        $guestData = [];
        $botData = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = $utcNow->copy()->subDays($i)->format('Y-m-d');
            $labels[] = now()->subDays($i)->format('M j');
            $userData[] = $userVisits->get($date, 0);
            $guestData[] = $guestVisits->get($date, 0);
            $botData[] = $botVisits->get($date, 0);
        }

        $datasets = [
            [
                'label' => 'Users',
                'data' => $userData,
                'borderColor' => '#6366f1',
                'backgroundColor' => 'rgba(99, 102, 241, 0.1)',
                'fill' => false,
                'tension' => 0.3,
            ],
            [
                'label' => 'Guests',
                'data' => $guestData,
                'borderColor' => '#10b981',
                'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                'fill' => false,
                'tension' => 0.3,
            ],
        ];

        if ($trackBots) {
            $datasets[] = [
                'label' => 'Bots',
                'data' => $botData,
                'borderColor' => '#f59e0b',
                'backgroundColor' => 'rgba(245, 158, 11, 0.1)',
                'fill' => false,
                'tension' => 0.3,
            ];
        }

        return [
            'datasets' => $datasets,
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
