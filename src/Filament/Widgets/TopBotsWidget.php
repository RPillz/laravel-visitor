<?php

namespace RPillz\LaravelVisitor\Filament\Widgets;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use RPillz\LaravelVisitor\Models\Visit;

class TopBotsWidget extends TableWidget
{
    protected static ?string $heading = 'Top Bots';

    public static function canView(): bool
    {
        return (bool) config('visitor.track_bots', true);
    }

    protected int|string|array $columnSpan = 'full';

    public function getTableRecordKey(Model|array $record): string
    {
        return is_array($record) ? ($record['bot_name'] ?? '') : ($record->bot_name ?? '');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Visit::query()
                    ->selectRaw('bot_name, COUNT(*) as visit_count')
                    ->whereNotNull('bot_name')
                    ->groupBy('bot_name')
                    ->orderByDesc('visit_count')
                    ->limit(20)
            )
            ->columns([
                TextColumn::make('bot_name')
                    ->label('Bot'),
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
