<?php

namespace RPillz\LaravelVisitor\Filament\Widgets;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use RPillz\LaravelVisitor\Models\Visit;

class TopPagesWidget extends TableWidget
{
    protected static ?string $heading = 'Top Pages';

    protected int|string|array $columnSpan = 'full';

    public function getTableRecordKey(Model | array $record): string
    {
        return is_array($record) ? $record['path'] : $record->path;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Visit::query()
                    ->selectRaw('path, COUNT(*) as visit_count')
                    ->groupBy('path')
                    ->orderByDesc('visit_count')
                    ->limit(20)
            )
            ->columns([
                TextColumn::make('path')
                    ->label('Page')
                    ->searchable(),
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
