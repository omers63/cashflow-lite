<?php

namespace App\Filament\Resources\UserResource\RelationManagers;

use App\Filament\Resources\TransactionResource;
use App\Models\Transaction;
use Filament\Actions;
use Filament\Forms;
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
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Transaction Type')
                    ->options([
                        'external_import' => 'External Bank Import',
                        'master_to_user_bank' => 'Master to User Bank',
                        'contribution' => 'Contribution',
                        'loan_repayment' => 'Loan Repayment',
                        'loan_disbursement' => 'Loan Disbursement',
                        'adjustment' => 'Adjustment',
                    ])
                    ->multiple(),

                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'complete' => 'Complete',
                        'failed' => 'Failed',
                        'reversed' => 'Reversed',
                    ])
                    ->multiple(),

                Tables\Filters\SelectFilter::make('from_account_exact')
                    ->label('From Account (exact)')
                    ->options(fn () => Transaction::query()
                        ->whereNotNull('from_account')
                        ->distinct()
                        ->orderBy('from_account')
                        ->pluck('from_account', 'from_account')
                        ->toArray()
                    )
                    ->multiple()
                    ->query(function ($query, array $data) {
                        $values = $data['values'] ?? [];

                        return $query
                            ->when($values, fn ($q, $v) => $q->whereIn('from_account', $v));
                    }),

                Tables\Filters\Filter::make('from_account')
                    ->label('From Account')
                    ->schema([
                        Forms\Components\TextInput::make('value')
                            ->label('Contains')
                            ->placeholder('e.g. External Bank, Master Bank'),
                    ])
                    ->query(function ($query, array $data) {
                        $value = $data['value'] ?? null;

                        return $query
                            ->when($value, fn ($q, $v) => $q->where('from_account', 'like', '%' . $v . '%'));
                    }),

                Tables\Filters\Filter::make('transaction_date')
                    ->label('Transaction Date')
                    ->schema([
                        Forms\Components\DatePicker::make('from')
                            ->label('From'),
                        Forms\Components\DatePicker::make('to')
                            ->label('To'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'], fn ($q, $date) => $q->whereDate('transaction_date', '>=', $date))
                            ->when($data['to'], fn ($q, $date) => $q->whereDate('transaction_date', '<=', $date));
                    }),
            ])
            ->recordActions([
                Actions\ViewAction::make()
                    ->url(fn ($record) => TransactionResource::getUrl('view', ['record' => $record])),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->paginated([10, 25, 50]);
    }
}
