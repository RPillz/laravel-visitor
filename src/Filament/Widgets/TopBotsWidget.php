<?php

namespace RPillz\LaravelVisitor\Filament\Widgets;

use Filament\Tables\Columns\IconColumn;
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

    public function getTableRecordKey(Model|array $record): string
    {
        return is_array($record) ? ($record['bot_name'] ?? '') : ($record->bot_name ?? '');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Visit::withoutGlobalScope('exclude_blocked')
                    ->selectRaw('bot_name, COUNT(*) as total_count, SUM(CASE WHEN is_blocked = 0 THEN 1 ELSE 0 END) as allowed_count, SUM(CASE WHEN is_blocked = 1 THEN 1 ELSE 0 END) as blocked_count, MAX(is_verified) as is_verified')
                    ->whereNotNull('bot_name')
                    ->groupBy('bot_name')
                    ->orderByDesc('total_count')
            )
            ->columns([
                TextColumn::make('bot_name')
                    ->label('Bot'),
                TextColumn::make('allowed_count')
                    ->label('Allowed')
                    ->sortable(),
                TextColumn::make('blocked_count')
                    ->label('Blocked')
                    ->sortable(),
                IconColumn::make('is_verified')
                    ->label('Verified')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-badge')
                    ->falseIcon('heroicon-o-x-mark')
                    ->trueColor('success')
                    ->falseColor('gray'),
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
