<?php

namespace RPillz\LaravelVisitor\Filament\Widgets;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use RPillz\LaravelVisitor\Models\Visit;

class LandingPagesWidget extends TableWidget
{
    protected static ?string $heading = 'Landing Pages';

    public function getTableRecordKey(Model|array $record): string
    {
        return is_array($record) ? $record['path'] : $record->path;
    }

    public function table(Table $table): Table
    {
        return $table
            ->description('Entry pages for multi-page sessions.')
            ->query(
                Visit::query()
                    ->selectRaw('path, COUNT(*) as entry_count')
                    ->whereNull('bot_name')
                    ->whereNotNull('session_id')
                    ->whereIn('id', function ($query) {
                        $query->selectRaw('MIN(id)')
                            ->from('visits')
                            ->whereNotNull('session_id')
                            ->whereNull('bot_name')
                            ->groupBy('session_id')
                            ->havingRaw('COUNT(*) > 1');
                    })
                    ->groupBy('path')
                    ->orderByDesc('entry_count')
            )
            ->columns([
                TextColumn::make('path')
                    ->label('Page')
                    ->searchable(),
                TextColumn::make('entry_count')
                    ->label('Entries')
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
            ->paginated([10, 25, 50]);
    }
}
