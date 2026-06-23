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

        // Timestamps are stored in UTC; use UTC on both sides so DATE() keys match.
        $utcNow = now()->utc();
        $start = $utcNow->copy()->subDays($days - 1)->startOfDay();

        $visits = Visit::query()
            ->where('created_at', '>=', $start)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('total', 'date');

        $labels = [];
        $data = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = $utcNow->copy()->subDays($i)->format('Y-m-d');
            $labels[] = now()->subDays($i)->format('M j');
            $data[] = $visits->get($date, 0);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Visits',
                    'data' => $data,
                    'borderColor' => '#6366f1',
                    'backgroundColor' => 'rgba(99, 102, 241, 0.1)',
                    'fill' => true,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
