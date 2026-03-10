<?php

namespace App\Filament\Widgets;

use App\Models\Loan;
use App\Models\MasterAccount;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class PendingLoanQueue extends BaseWidget
{
    protected static ?string $heading = 'Pending Loan Queue';
    protected static ?int $sort = 8;
    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Loan::query()->pendingPrioritized()
            )
            ->columns([
                Tables\Columns\TextColumn::make('loan_id')
                    ->label('Loan ID')
                    ->searchable(),
                Tables\Columns\TextColumn::make('member.user.name')
                    ->label('Member')
                    ->searchable(),
                Tables\Columns\TextColumn::make('original_amount')
                    ->label('Amount')
                    ->money('USD')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_emergency')
                    ->label('Emergency')
                    ->boolean()
                    ->trueIcon('heroicon-o-fire')
                    ->trueColor('danger')
                    ->falseIcon('heroicon-o-minus'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Requested')
                    ->since()
                    ->sortable(),
            ])
            ->emptyStateHeading('No pending loans')
            ->emptyStateDescription('There are no loan requests awaiting approval.')
            ->emptyStateIcon('heroicon-o-check-circle')
            ->paginated(false);
    }

    public function getTableDescription(): ?string
    {
        $masterFund = MasterAccount::where('account_type', 'master_fund')->first();
        $available = $masterFund ? number_format((float) $masterFund->balance, 2) : '0.00';
        $pendingTotal = number_format((float) Loan::where('status', 'pending')->sum('original_amount'), 2);

        return "Fund available: \${$available} · Pending disbursements: \${$pendingTotal}";
    }
}
