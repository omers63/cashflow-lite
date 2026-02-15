<?php

namespace App\Filament\Widgets;

use App\Models\MasterAccount;
use App\Models\User;
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
        
        return [
            Stat::make('Master Bank Balance', '$' . number_format($masterBank->balance ?? 0, 2))
                ->description('Total system funds')
                ->descriptionIcon('heroicon-o-building-library')
                ->color('success'),
            
            Stat::make('Master Fund Balance', '$' . number_format($masterFund->balance ?? 0, 2))
                ->description('Available for loans')
                ->descriptionIcon('heroicon-o-banknotes')
                ->color('primary'),
            
            Stat::make('Active Users', User::active()->count())
                ->description('Total active accounts')
                ->descriptionIcon('heroicon-o-users')
                ->color('info'),
            
            Stat::make('Active Loans', Loan::active()->count())
                ->description('$' . number_format(Loan::active()->sum('outstanding_balance'), 2) . ' outstanding')
                ->descriptionIcon('heroicon-o-currency-dollar')
                ->color('warning'),
            
            Stat::make('Open Exceptions', Exception::open()->count())
                ->description(Exception::overdue()->count() . ' overdue')
                ->descriptionIcon('heroicon-o-exclamation-triangle')
                ->color(Exception::open()->count() > 0 ? 'danger' : 'success'),
        ];
    }
}
