<?php

namespace App\Filament\Resources;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Schemas\Schema;
use Filament\Schemas\Components;
use Spatie\Activitylog\Models\Activity;

class NotificationResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-bell';

    protected static ?string $navigationLabel = 'Notifications';

    protected static ?string $modelLabel = 'Notification';

    protected static ?string $pluralModelLabel = 'Notifications';

    protected static string|\UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 99;

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('description')
                    ->label('Message')
                    ->limit(80)
                    ->searchable(),
                Tables\Columns\TextColumn::make('log_name')
                    ->label('Channel')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('causer.name')
                    ->label('User')
                    ->sortable()
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('subject_type')
                    ->label('Subject Type')
                    ->formatStateUsing(fn (?string $state) => $state ? class_basename($state) : null)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date')
                    ->since()
                    ->sortable(),
            ])
            ->filters([])
            ->recordUrl(null)
            ->recordAction(null)
            ->actions([])
            ->bulkActions([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Components\Section::make('Notification Details')
                    ->schema([
                        Infolists\Components\TextEntry::make('description')
                            ->label('Message'),
                        Infolists\Components\TextEntry::make('log_name')
                            ->label('Channel'),
                        Infolists\Components\TextEntry::make('causer.name')
                            ->label('User'),
                        Infolists\Components\TextEntry::make('subject_type')
                            ->label('Subject Type')
                            ->formatStateUsing(fn (?string $state) => $state ? class_basename($state) : null),
                        Infolists\Components\TextEntry::make('subject_id')
                            ->label('Subject ID'),
                        Infolists\Components\KeyValueEntry::make('properties')
                            ->label('Details')
                            ->visible(fn ($record) => filled($record?->properties)),
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Created At')
                            ->dateTime(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => NotificationResource\Pages\ListNotifications::route('/'),
            'view' => NotificationResource\Pages\ViewNotification::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function canForceDelete($record): bool
    {
        return false;
    }

    public static function canRestore($record): bool
    {
        return false;
    }
}

