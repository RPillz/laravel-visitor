<?php

namespace RPillz\LaravelVisitor\Filament\Resources;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use RPillz\LaravelVisitor\Filament\Resources\VisitorIgnoreResource\Pages\ListVisitorIgnores;
use RPillz\LaravelVisitor\Models\VisitorIgnore;

class VisitorIgnoreResource extends Resource
{
    protected static ?string $model = VisitorIgnore::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-no-symbol';

    protected static ?string $navigationLabel = 'Ignore / Block List';

    protected static \UnitEnum|string|null $navigationGroup = 'Analytics';

    protected static ?int $navigationSort = 100;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('type')
                ->options([
                    'ip' => 'IP Address',
                    'user_id' => 'User ID',
                    'user_agent' => 'User Agent',
                    'header_fingerprint' => 'Header Fingerprint',
                ])
                ->required(),
            TextInput::make('value')
                ->label('Value')
                ->helperText('User agent values support wildcards (* and ?).')
                ->required(),
            Toggle::make('is_blocked')
                ->label('Block request (return 403)')
                ->default(false),
            DateTimePicker::make('expires_at')
                ->label('Expires at')
                ->helperText('Leave blank for a permanent entry.')
                ->nullable(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'ip' => 'warning',
                        'user_id' => 'danger',
                        'user_agent' => 'info',
                        'header_fingerprint' => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('value')
                    ->searchable()
                    ->copyable(),
                IconColumn::make('is_blocked')
                    ->label('Action')
                    ->boolean()
                    ->trueColor('danger')
                    ->falseColor('gray')
                    ->trueIcon('heroicon-o-shield-exclamation')
                    ->falseIcon('heroicon-o-eye'),
                IconColumn::make('is_automatic')
                    ->boolean()
                    ->trueColor('warning')
                    ->falseColor('gray')
                    ->trueIcon('heroicon-o-cpu-chip')
                    ->falseIcon('heroicon-o-user')
                    ->label('Source'),
                TextColumn::make('expires_at')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('Never'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_blocked')
                    ->label('Blocked')
                    ->trueLabel('Blocked only')
                    ->falseLabel('Ignored only'),
                SelectFilter::make('is_automatic')
                    ->label('Source')
                    ->options([
                        '0' => 'User',
                        '1' => 'System',
                    ]),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->actions([
                DeleteAction::make(),
            ])
            ->emptyStateHeading('No ignore or block rules')
            ->emptyStateDescription('Add IP addresses, user IDs, or user agents to silently ignore or block visitors.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVisitorIgnores::route('/'),
        ];
    }
}
