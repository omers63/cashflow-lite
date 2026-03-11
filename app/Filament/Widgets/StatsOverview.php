<?php

namespace App\Filament\Widgets;

use App\Models\MasterAccount;
use App\Models\Member;
use App\Models\Loan;
use App\Models\Exception;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $masterBank = MasterAccount::where('account_type', 'master_bank')->first();
        $masterFund = MasterAccount::where('account_type', 'master_fund')->first();
        $activeLoans = Loan::active();
        $openExceptions = Exception::open()->count();
        $overdueExceptions = Exception::overdue()->count();

        return [
            Stat::make('Master Bank Balance', '$' . number_format($masterBank->balance ?? 0, 2))
                ->description('Total system bank funds')
                ->descriptionIcon('heroicon-m-building-library')
                ->color('success')
                ->chart([7, 3, 4, 5, 6, 3, 5, 7, 9, 8]),

            Stat::make('Master Fund Balance', '$' . number_format($masterFund->balance ?? 0, 2))
                ->description('Available for member loans')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('primary')
                ->chart([5, 6, 7, 5, 8, 6, 9, 7, 8, 9]),

            Stat::make('Active Members', Member::count())
                ->description('Total registered members')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('info')
                ->chart([3, 4, 4, 5, 5, 5, 6, 6, 7, 7]),

            Stat::make('Active Loans', $activeLoans->count())
                ->description('$' . number_format($activeLoans->sum('outstanding_balance'), 2) . ' outstanding')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('warning')
                ->chart([4, 5, 6, 5, 7, 6, 8, 6, 7, 8]),

            Stat::make('Open Exceptions', $openExceptions)
                ->description($overdueExceptions . ' overdue')
                ->descriptionIcon($openExceptions > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($openExceptions > 0 ? 'danger' : 'success')
                ->chart([2, 3, 2, 4, 3, 2, 1, 2, 1, $openExceptions]),
        ];
    }
}
