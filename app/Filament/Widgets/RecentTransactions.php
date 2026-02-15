<?php

namespace App\Filament\Widgets;

use App\Models\Transaction;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentTransactions extends BaseWidget
{
    protected static ?int $sort = 2;
    protected int | string | array $columnSpan = 'full';

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
                Tables\Columns\BadgeColumn::make('type'),
                Tables\Columns\TextColumn::make('amount')
                    ->money('USD'),
                Tables\Columns\TextColumn::make('user.name'),
                Tables\Columns\BadgeColumn::make('status'),
            ]);
    }
}
