<?php

namespace App\Filament\Resources\UserResource\RelationManagers;

use App\Filament\Resources\TransactionResource;
use Filament\Actions;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class TransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'transactions';

    protected static ?string $title = 'Transactions';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-arrow-path';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('transaction_id')
                    ->label('ID')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('transaction_date')
                    ->dateTime('M d, Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->colors([
                        'primary' => 'external_import',
                        'success' => 'contribution',
                        'warning' => 'loan_repayment',
                        'danger' => 'loan_disbursement',
                        'info' => 'master_to_user_bank',
                        'secondary' => 'adjustment',
                    ])
                    ->formatStateUsing(fn (?string $state) => $state ? str_replace('_', ' ', ucfirst($state)) : ''),

                Tables\Columns\TextColumn::make('from_account')
                    ->limit(20)
                    ->tooltip(fn ($record) => $record->from_account),

                Tables\Columns\TextColumn::make('to_account')
                    ->limit(20)
                    ->tooltip(fn ($record) => $record->to_account),

                Tables\Columns\TextColumn::make('amount')
                    ->money('USD')
                    ->sortable()
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->money('USD')
                            ->label('Total'),
                    ]),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'complete',
                        'danger' => 'failed',
                        'secondary' => 'reversed',
                    ]),
            ])
            ->defaultSort('transaction_date', 'desc')
            ->recordActions([
                Actions\ViewAction::make()
                    ->url(fn ($record) => TransactionResource::getUrl('view', ['record' => $record])),
            ])
            ->paginated([10, 25, 50]);
    }
}
