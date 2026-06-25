<?php

namespace RPillz\LaravelVisitor\Filament\Widgets;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use RPillz\LaravelVisitor\Models\Visit;

class BouncePagesWidget extends TableWidget
{
    protected static ?string $heading = 'Bounce Pages';

    public function getTableRecordKey(Model|array $record): string
    {
        return is_array($record) ? $record['path'] : $record->path;
    }

    public function table(Table $table): Table
    {
        return $table
            ->description('Pages where visitors viewed only one page and left.')
            ->query(
                Visit::query()
                    ->selectRaw('path, COUNT(*) as bounce_count')
                    ->whereNull('bot_name')
                    ->whereNotNull('session_id')
                    ->whereIn('session_id', function ($query) {
                        $query->select('session_id')
                            ->from('visits')
                            ->whereNotNull('session_id')
                            ->whereNull('bot_name')
                            ->groupBy('session_id')
                            ->havingRaw('COUNT(*) = 1');
                    })
                    ->groupBy('path')
                    ->orderByDesc('bounce_count')
            )
            ->columns([
                TextColumn::make('path')
                    ->label('Page')
                    ->searchable(),
                TextColumn::make('bounce_count')
                    ->label('Bounces')
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
