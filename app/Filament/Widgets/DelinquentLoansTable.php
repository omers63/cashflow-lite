<?php

namespace App\Filament\Widgets;

use App\Models\Loan;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class DelinquentLoansTable extends BaseWidget
{
    protected static ?string $heading = 'Delinquent Loans';
    protected static ?int $sort = 7;
    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => Loan::query()
                ->where('status', 'active')
                ->where('next_payment_date', '<', now())
                ->orderBy('next_payment_date')
            )
            ->columns([
                Tables\Columns\TextColumn::make('loan_id')
                    ->label('Loan ID')
                    ->searchable(),
                Tables\Columns\TextColumn::make('member.user.name')
                    ->label('Member')
                    ->searchable(),
                Tables\Columns\TextColumn::make('outstanding_balance')
                    ->money('USD')
                    ->sortable(),
                Tables\Columns\TextColumn::make('next_payment_date')
                    ->label('Due Date')
                    ->date('M d, Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('days_overdue')
                    ->label('Days Overdue')
                    ->badge()
                    ->color(fn (int $state) => match (true) {
                        $state > 60 => 'danger',
                        $state > 30 => 'warning',
                        default => 'info',
                    })
                    ->suffix(' days'),
                Tables\Columns\TextColumn::make('installment_amount')
                    ->label('Payment Due')
                    ->money('USD'),
            ])
            ->emptyStateHeading('No delinquent loans')
            ->emptyStateDescription('All active loans are current on payments.')
            ->emptyStateIcon('heroicon-o-check-circle')
            ->paginated(false);
    }
}
