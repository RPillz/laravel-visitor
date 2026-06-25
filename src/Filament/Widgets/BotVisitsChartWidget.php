<?php

namespace RPillz\LaravelVisitor\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use RPillz\LaravelVisitor\Models\Visit;

class BotVisitsChartWidget extends ChartWidget
{
    protected ?string $heading = 'Bot Traffic';

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '175px';

    public ?string $filter = '30';

    public static function canView(): bool
    {
        return (bool) config('visitor.track_bots', true);
    }

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
        $trackBlocks = config('visitor.log_blocks', false);

        $groupedBy = fn (callable $scope) => Visit::withoutGlobalScope('exclude_blocked')
            ->where('created_at', '>=', $start)
            ->tap($scope)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('total', 'date');

        $verifiedVisits = $groupedBy(fn ($q) => $q->whereNotNull('bot_name')->where('is_verified', true)->where('is_blocked', false));
        $otherBotVisits = $groupedBy(fn ($q) => $q->whereNotNull('bot_name')->where('is_verified', false)->where('is_blocked', false));
        $blockedVisits = $trackBlocks ? $groupedBy(fn ($q) => $q->where('is_blocked', true)) : collect();

        $labels = [];
        $verifiedData = [];
        $otherData = [];
        $blockedData = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = $utcNow->copy()->subDays($i)->format('Y-m-d');
            $labels[] = now()->subDays($i)->format('M j');
            $verifiedData[] = $verifiedVisits->get($date, 0);
            $otherData[] = $otherBotVisits->get($date, 0);
            $blockedData[] = $blockedVisits->get($date, 0);
        }

        $datasets = [
            [
                'label' => 'Verified Crawlers',
                'data' => $verifiedData,
                'borderColor' => '#10b981',
                'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                'fill' => false,
                'tension' => 0.3,
            ],
            [
                'label' => 'Other Bots',
                'data' => $otherData,
                'borderColor' => '#f59e0b',
                'backgroundColor' => 'rgba(245, 158, 11, 0.1)',
                'fill' => false,
                'tension' => 0.3,
            ],
        ];

        if ($trackBlocks) {
            $datasets[] = [
                'label' => 'Blocked',
                'data' => $blockedData,
                'borderColor' => '#ef4444',
                'backgroundColor' => 'rgba(239, 68, 68, 0.1)',
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
