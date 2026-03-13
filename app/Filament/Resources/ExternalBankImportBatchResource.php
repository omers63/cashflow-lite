<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ExternalBankImportBatchResource\Pages;
use App\Models\ExternalBankImportBatch;
use Filament\Actions;
use Filament\Infolists;
use Filament\Resources\Resource;
use Filament\Schemas\Components;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class ExternalBankImportBatchResource extends Resource
{
    protected static ?string $model = ExternalBankImportBatch::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-inbox-arrow-down';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    /**
     * Hide this resource from the main navigation; it is now accessed from
     * the Account Management page's External Bank Accounts table header.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Imported At')
                    ->dateTime('M d, Y g:i A')
                    ->sortable()
                    ->description(fn (ExternalBankImportBatch $record) => $record->created_at?->diffForHumans()),

                Tables\Columns\TextColumn::make('externalBankAccount.bank_name')
                    ->label('Bank')
                    ->badge()
                    ->color('info')
                    ->searchable(),

                Tables\Columns\TextColumn::make('source_type')
                    ->label('Source')
                    ->badge()
                    ->colors([
                        'primary' => 'file',
                        'success' => 'manual',
                    ])
                    ->formatStateUsing(fn (?string $state): string => $state ? ucfirst($state) : ''),

                Tables\Columns\TextColumn::make('source_name')
                    ->label('File / Label')
                    ->limit(30)
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('rows_total')
                    ->label('Rows')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('rows_new')
                    ->label('New')
                    ->color('success')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('rows_duplicates')
                    ->label('Duplicates')
                    ->color('warning')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('rows_posted')
                    ->label('Posted to Master')
                    ->color('primary')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Imported By')
                    ->placeholder('System'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('external_bank_account_id')
                    ->label('Bank')
                    ->relationship('externalBankAccount', 'bank_name')
                    ->multiple()
                    ->preload(),

                Tables\Filters\SelectFilter::make('source_type')
                    ->label('Source')
                    ->options([
                        'file' => 'File',
                        'manual' => 'Manual',
                    ]),
            ])
            ->recordUrl(fn (ExternalBankImportBatch $record) => static::getUrl('view', ['record' => $record]))
            ->recordActions([
                Actions\ViewAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped();
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Components\Section::make('Session Summary')
                    ->schema([
                        Infolists\Components\TextEntry::make('externalBankAccount.bank_name')
                            ->label('Bank')
                            ->badge()
                            ->color('info'),

                        Infolists\Components\TextEntry::make('source_type')
                            ->label('Source')
                            ->badge()
                            ->color(fn (?string $state) => $state === 'file' ? 'primary' : 'success')
                            ->formatStateUsing(fn (?string $state): string => $state ? ucfirst($state) : ''),

                        Infolists\Components\TextEntry::make('source_name')
                            ->label('File / Label')
                            ->placeholder('—'),

                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Imported At')
                            ->dateTime(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Components\Section::make('Row Stats')
                    ->schema([
                        Infolists\Components\TextEntry::make('rows_total')
                            ->label('Total Rows')
                            ->numeric()
                            ->badge()
                            ->color('gray'),
                        Infolists\Components\TextEntry::make('rows_new')
                            ->label('New')
                            ->numeric()
                            ->badge()
                            ->color('success'),
                        Infolists\Components\TextEntry::make('rows_duplicates')
                            ->label('Duplicates')
                            ->numeric()
                            ->badge()
                            ->color('warning'),
                        Infolists\Components\TextEntry::make('rows_posted')
                            ->label('Posted to Master')
                            ->numeric()
                            ->badge()
                            ->color('primary'),
                    ])
                    ->columns(4)
                    ->columnSpanFull(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Resources\ExternalBankImportBatchResource\RelationManagers\ImportsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListExternalBankImportBatches::route('/'),
            'view' => Pages\ViewExternalBankImportBatch::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}

