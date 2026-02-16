<?php

namespace App\Filament\Widgets;

use App\Models\Transaction;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentTransactions extends BaseWidget
{
    protected static ?int $sort = 2;
    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Transaction::query()->latest()->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('transaction_date')
                    ->dateTime('M d, H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('transaction_id')
                    ->searchable(),
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->color(fn($state) => match ($state) {
                        'external_import' => 'primary',
                        'contribution' => 'success',
                        'loan_repayment' => 'warning',
                        'loan_disbursement' => 'danger',
                        'master_to_user_bank' => 'info',
                        'adjustment' => 'secondary',
                        default => 'secondary',
                    })
                    ->formatStateUsing(fn($state) => str_replace('_', ' ', ucfirst($state))),
                Tables\Columns\TextColumn::make('amount')
                    ->money('USD'),
                Tables\Columns\TextColumn::make('user.name'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn($state) => match ($state) {
                        'pending' => 'warning',
                        'complete' => 'success',
                        'failed' => 'danger',
                        'reversed' => 'secondary',
                        default => 'secondary',
                    }),
            ]);
    }
}
