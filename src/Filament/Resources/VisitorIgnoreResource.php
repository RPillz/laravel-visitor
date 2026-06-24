<?php

namespace RPillz\LaravelVisitor\Filament\Resources;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use RPillz\LaravelVisitor\Filament\Resources\VisitorIgnoreResource\Pages\ListVisitorIgnores;
use RPillz\LaravelVisitor\Models\VisitorIgnore;

class VisitorIgnoreResource extends Resource
{
    protected static ?string $model = VisitorIgnore::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-no-symbol';

    protected static ?string $navigationLabel = 'Ignore List';

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
                ])
                ->required(),
            TextInput::make('value')
                ->label('Value')
                ->helperText('User agent values support wildcards (* and ?).')
                ->required(),
            Toggle::make('is_blocked')
                ->label('Block request (return 403)')
                ->default(false),
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
                        default => 'gray',
                    }),
                TextColumn::make('value')
                    ->searchable()
                    ->copyable(),
                IconColumn::make('is_blocked')
                    ->boolean()
                    ->trueColor('danger')
                    ->falseColor('gray')
                    ->trueIcon('heroicon-o-shield-exclamation')
                    ->falseIcon('heroicon-o-eye'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->actions([
                DeleteAction::make(),
            ])
            ->emptyStateHeading('No ignored visitors')
            ->emptyStateDescription('Add IP addresses, user IDs, or user agents to prevent them from being tracked.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVisitorIgnores::route('/'),
        ];
    }
}
