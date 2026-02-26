<?php

namespace App\Filament\Resources\ExternalBankAccountResource\RelationManagers;

use Filament\Actions;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ImportsRelationManager extends RelationManager
{
    protected static string $relationship = 'imports';

    protected static ?string $title = 'Transactions';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-arrow-path';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('transaction_date')
                    ->label('Transaction Date')
                    ->dateTime('M d, Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('external_ref_id')
                    ->label('Reference')
                    ->searchable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('description')
                    ->limit(40)
                    ->tooltip(fn($record) => $record->description),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Amount')
                    ->money('USD')
                    ->sortable()
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->money('USD')
                            ->label('Total'),
                    ]),

                Tables\Columns\IconColumn::make('is_duplicate')
                    ->label('Duplicate')
                    ->boolean(),

                Tables\Columns\IconColumn::make('imported_to_master')
                    ->label('Imported to Master')
                    ->boolean(),
            ])
            ->defaultSort('transaction_date', 'desc')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_duplicate')
                    ->label('Duplicates'),
                Tables\Filters\TernaryFilter::make('imported_to_master')
                    ->label('Imported to Master'),
            ])
            ->recordActions([])
            ->toolbarActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make()
                        ->label('Delete Transactions'),
                ]),
            ])
            ->paginated([10, 25, 50]);
    }
}

