<?php

namespace App\Filament\Resources\LoanResource\RelationManagers;

use App\Filament\Resources\TransactionResource;
use App\Models\Transaction;
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
                    ->date('M d, Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->colors([
                        'danger' => 'loan_disbursement',
                        'warning' => 'loan_repayment',
                    ])
                    ->formatStateUsing(fn (?string $state) => $state ? str_replace('_', ' ', ucfirst($state)) : ''),

                Tables\Columns\TextColumn::make('amount')
                    ->money('USD')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'complete',
                        'danger' => 'failed',
                        'secondary' => 'reversed',
                    ]),

                Tables\Columns\TextColumn::make('notes')
                    ->limit(40)
                    ->placeholder('—'),
            ])
            ->defaultSort('transaction_date', 'desc')
            ->recordActions([
                Actions\ViewAction::make()
                    ->url(fn (Transaction $record) => TransactionResource::getUrl('view', ['record' => $record])),
            ])
            ->emptyStateHeading('No transactions')
            ->emptyStateDescription('Disbursement and repayment transactions for this loan will appear here.')
            ->emptyStateIcon('heroicon-o-arrow-path');
    }
}
