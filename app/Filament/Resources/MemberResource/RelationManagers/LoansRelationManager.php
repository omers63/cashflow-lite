<?php

namespace App\Filament\Resources\MemberResource\RelationManagers;

use App\Filament\Resources\LoanResource;
use App\Models\Loan;
use Filament\Actions;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class LoansRelationManager extends RelationManager
{
    protected static string $relationship = 'loans';

    protected static ?string $title = 'Loans';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-banknotes';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('loan_id')
                    ->label('Loan ID')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                Tables\Columns\IconColumn::make('is_emergency')
                    ->label('Emergency')
                    ->boolean()
                    ->trueColor('danger')
                    ->sortable(),

                Tables\Columns\TextColumn::make('origination_date')
                    ->label('Origination Date')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('original_amount')
                    ->label('Amount')
                    ->money('USD')
                    ->sortable(),

                Tables\Columns\TextColumn::make('installment_amount')
                    ->label('Installment')
                    ->money('USD')
                    ->sortable(),

                Tables\Columns\TextColumn::make('outstanding_balance')
                    ->label('Balance')
                    ->money('USD')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'active',
                        'primary' => 'paid_off',
                        'danger' => 'defaulted',
                        'secondary' => 'cancelled',
                    ]),

                Tables\Columns\TextColumn::make('next_payment_date')
                    ->label('Next Payment')
                    ->date()
                    ->sortable()
                    ->color(fn (Loan $record) => $record->isDelinquent() ? 'danger' : null),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                Actions\ActionGroup::make([
                    Actions\ViewAction::make()
                        ->label('View')
                        ->tooltip('View')
                        ->url(fn (Loan $record) => LoanResource::getUrl('view', ['record' => $record])),
                ])
                    ->label('')
                    ->icon('heroicon-o-ellipsis-horizontal'),
            ])
            ->emptyStateHeading('No loans yet')
            ->emptyStateDescription('This member has not taken any loans.')
            ->emptyStateIcon('heroicon-o-banknotes');
    }
}

