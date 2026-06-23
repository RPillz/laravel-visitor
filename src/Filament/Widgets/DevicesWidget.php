<?php

namespace RPillz\LaravelVisitor\Filament\Widgets;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use RPillz\LaravelVisitor\Models\Visit;

class DevicesWidget extends TableWidget
{
    protected static ?string $heading = 'Devices & Browsers';

    protected int|string|array $columnSpan = 'full';

    public function getTableRecordKey(Model | array $record): string
    {
        if (is_array($record)) {
            return implode('|', [$record['device_type'], $record['browser'], $record['os']]);
        }

        return implode('|', [$record->device_type, $record->browser, $record->os]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Visit::query()
                    ->selectRaw('device_type, browser, os, COUNT(*) as visit_count')
                    ->whereNotNull('device_type')
                    ->groupBy('device_type', 'browser', 'os')
                    ->orderByDesc('visit_count')
                    ->limit(20)
            )
            ->columns([
                TextColumn::make('device_type')
                    ->label('Device')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'mobile' => 'warning',
                        'tablet' => 'info',
                        default => 'success',
                    }),
                TextColumn::make('browser')
                    ->label('Browser'),
                TextColumn::make('os')
                    ->label('OS'),
                TextColumn::make('visit_count')
                    ->label('Visits')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('period')
                    ->label('Period')
                    ->placeholder('All time')
                    ->options([
                        '7' => 'Last 7 days',
                        '30' => 'Last 30 days',
                        '90' => 'Last 90 days',
                    ])
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->where('created_at', '>=', now()->subDays((int) $data['value']))
                        : $query
                    ),
            ])
            ->paginated(false);
    }
}
