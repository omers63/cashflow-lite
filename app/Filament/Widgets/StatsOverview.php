<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\AccountManagement;
use App\Filament\Resources\ExceptionResource;
use App\Filament\Resources\LoanResource;
use App\Filament\Resources\MemberResource;
use App\Models\Exception;
use App\Models\Loan;
use App\Models\MasterAccount;
use App\Models\Member;
use Filament\Widgets\Widget;

class StatsOverview extends Widget
{
    protected string $view = 'filament.widgets.stats-overview';

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    public function getSummary(): array
    {
        $masterBank = MasterAccount::where('account_type', 'master_bank')->first();
        $masterFund = MasterAccount::where('account_type', 'master_fund')->first();
        $activeLoans = Loan::active();
        $openExceptions = Exception::open()->count();
        $overdueExceptions = Exception::overdue()->count();

        return [
            'bank_balance'       => $masterBank?->balance ?? 0,
            'fund_balance'       => $masterFund?->balance ?? 0,
            'total_members'      => Member::count(),
            'active_loans'       => $activeLoans->count(),
            'loan_outstanding'   => $activeLoans->sum('outstanding_balance'),
            'open_exceptions'    => $openExceptions,
            'overdue_exceptions' => $overdueExceptions,
        ];
    }

    public function getAccountManagementUrl(): string
    {
        return AccountManagement::getUrl();
    }

    public function getMembersUrl(): string
    {
        return MemberResource::getUrl('index');
    }

    public function getLoansUrl(): string
    {
        return LoanResource::getUrl('index');
    }

    public function getExceptionsUrl(): string
    {
        return ExceptionResource::getUrl('index');
    }
}
