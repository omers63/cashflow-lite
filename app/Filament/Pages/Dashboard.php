<?php

namespace App\Filament\Pages;

use App\Models\Exception;
use App\Models\Loan;
use App\Models\MasterAccount;
use App\Models\Member;
use App\Models\Setting;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-home';

    protected string $view = 'filament.pages.dashboard';

    public function getWidgets(): array
    {
        $all = [
            'quick_actions'         => \App\Filament\Widgets\QuickActions::class,
            'stats_overview'        => \App\Filament\Widgets\StatsOverview::class,
            'open_exceptions'       => \App\Filament\Widgets\OpenExceptions::class,
            'reconciliation_status' => \App\Filament\Widgets\ReconciliationStatus::class,
            'cash_flow_trend_chart' => \App\Filament\Widgets\CashFlowTrendChart::class,
            'collections_progress'  => \App\Filament\Widgets\CollectionsProgressGauge::class,
            'delinquent_loans'      => \App\Filament\Widgets\DelinquentLoansTable::class,
            'pending_loan_queue'    => \App\Filament\Widgets\PendingLoanQueue::class,
            'external_bank_imports' => \App\Filament\Widgets\ExternalBankImportsSummary::class,
            'balance_integrity'     => \App\Filament\Widgets\BalanceIntegrityCheck::class,
            'upcoming_payments'     => \App\Filament\Widgets\UpcomingPaymentsCalendar::class,
            'recent_transactions'   => \App\Filament\Widgets\RecentTransactions::class,
        ];

        $json    = Setting::get('dashboard_widgets_admin');
        $enabled = $json ? json_decode($json, true) : null;

        if ($enabled === null) {
            return array_values($all);
        }

        return array_values(array_filter($all, fn (string $class, string $key) =>
            ($enabled[$key] ?? true), ARRAY_FILTER_USE_BOTH));
    }

    public function getSummary(): array
    {
        $masterBank = MasterAccount::where('account_type', 'master_bank')->first();
        $masterFund = MasterAccount::where('account_type', 'master_fund')->first();
        $activeLoans = Loan::active();

        return [
            'bank_balance'    => $masterBank?->balance ?? 0,
            'fund_balance'    => $masterFund?->balance ?? 0,
            'total_members'   => Member::count(),
            'active_loans'    => $activeLoans->count(),
            'loan_outstanding' => $activeLoans->sum('outstanding_balance'),
            'open_exceptions' => Exception::open()->count(),
            'overdue_exceptions' => Exception::overdue()->count(),
        ];
    }
}
