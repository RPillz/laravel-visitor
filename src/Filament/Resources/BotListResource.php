<?php

namespace RPillz\LaravelVisitor\Filament\Resources;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use RPillz\LaravelVisitor\Filament\Resources\BotListResource\Pages\ListBots;
use RPillz\LaravelVisitor\Models\Visit;
use RPillz\LaravelVisitor\Models\VisitorIgnore;

class BotListResource extends Resource
{
    protected static ?string $model = Visit::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-bug-ant';

    protected static ?string $navigationLabel = 'Bot List';

    protected static \UnitEnum|string|null $navigationGroup = 'Analytics';

    protected static ?int $navigationSort = 50;

    public static function shouldRegisterNavigation(): bool
    {
        return (bool) config('visitor.track_bots', true);
    }

    public static function table(Table $table): Table
    {
        // One real Visit row per bot+fingerprint (the most recent), with a visit count appended.
        // Using real IDs means Filament's standard record resolution works with no overrides.
        $latestPerBot = Visit::withoutGlobalScope('exclude_blocked')
            ->selectRaw('MAX(id)')
            ->whereNotNull('bot_name')
            ->groupBy('bot_name', 'header_fingerprint');

        return $table
            ->query(
                Visit::withoutGlobalScope('exclude_blocked')
                    ->selectRaw('*, (SELECT COUNT(*) FROM visits v2 WHERE v2.bot_name = visits.bot_name AND (v2.header_fingerprint = visits.header_fingerprint OR (v2.header_fingerprint IS NULL AND visits.header_fingerprint IS NULL))) as visit_count')
                    ->whereIn('id', $latestPerBot)
                    ->orderByDesc('visit_count')
            )
            ->columns([
                TextColumn::make('bot_name')
                    ->label('Bot')
                    ->searchable(),
                TextColumn::make('header_fingerprint')
                    ->label('Fingerprint')
                    ->limit(12)
                    ->copyable()
                    ->placeholder('—'),
                TextColumn::make('visit_count')
                    ->label('Visits')
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
            ->actions([
                Action::make('block')
                    ->label('Block')
                    ->icon('heroicon-o-shield-exclamation')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading(fn (Visit $record) => 'Block '.$record->bot_name.'?')
                    ->modalDescription('This will block this bot by its header fingerprint if one is recorded, otherwise by a wildcard user agent rule.')
                    ->action(function (Visit $record): void {
                        if (filled($record->header_fingerprint)) {
                            VisitorIgnore::updateOrCreate(
                                ['type' => 'header_fingerprint', 'value' => $record->header_fingerprint],
                                ['is_blocked' => true, 'is_automatic' => false],
                            );
                        } else {
                            VisitorIgnore::updateOrCreate(
                                ['type' => 'user_agent', 'value' => '*'.$record->bot_name.'*'],
                                ['is_blocked' => true, 'is_automatic' => false],
                            );
                        }

                        Notification::make()
                            ->title($record->bot_name.' blocked')
                            ->success()
                            ->send();
                    }),
            ])
            ->emptyStateHeading('No bots tracked')
            ->emptyStateDescription('Bot visits will appear here when bot tracking is enabled.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBots::route('/'),
        ];
    }
}
