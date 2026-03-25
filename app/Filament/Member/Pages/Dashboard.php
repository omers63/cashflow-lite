<?php

namespace App\Filament\Member\Pages;

use App\Models\Member;
use App\Models\Setting;
use App\Models\Transaction;
use App\Services\MonthlyCollectionsService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;

class Dashboard extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-home';
    protected static ?string $navigationLabel = 'Dashboard';
    protected static ?string $title = 'Member Dashboard';
    protected string $view = 'filament.member.pages.dashboard';

    public function getTitle(): string|Htmlable
    {
        return 'Member Dashboard';
    }

    /**
     * Check whether a member dashboard widget section is enabled.
     */
    public function isWidgetEnabled(string $key): bool
    {
        static $enabled = null;

        if ($enabled === null) {
            $json = Setting::get('dashboard_widgets_member');
            $enabled = $json ? json_decode($json, true) : [];
        }

        // If no settings saved yet, everything is enabled by default
        if (empty($enabled)) {
            return true;
        }

        return (bool) ($enabled[$key] ?? true);
    }

    public function getMember(): ?Member
    {
        return auth()->user()?->member;
    }

    /**
     * Summary metrics for the member dashboard.
     *
     * @return array{
     *   bank_balance: float,
     *   fund_balance: float,
     *   outstanding_loans: float,
     *   next_payment_date: \Carbon\CarbonInterface|null,
     *   next_payment_amount: float|null,
     *   shortfall: float,
     *   expected_total: float,
     *   realized_total: float,
     *   period_label: string
     * }
     */
    public function getSummary(): array
    {
        $member = $this->getMember();
        if (!$member) {
            return [
                'bank_balance' => 0.0,
                'fund_balance' => 0.0,
                'outstanding_loans' => 0.0,
                'next_payment_date' => null,
                'next_payment_amount' => null,
                'shortfall' => 0.0,
                'expected_total' => 0.0,
                'realized_total' => 0.0,
                'period_label' => '',
            ];
        }

        $now = Carbon::now();
        $periodMonth = (int) $now->copy()->subMonth()->format('n');
        $periodYear = (int) $now->copy()->subMonth()->format('Y');
        $periodLabel = Carbon::create($periodYear, $periodMonth, 1)->format('F Y');

        $collectionsService = app(MonthlyCollectionsService::class);
        $unrealized = $collectionsService->getUnrealizedMembers($periodYear, $periodMonth);
        $row = $unrealized[$member->id] ?? null;

        $expectedContribution = $row['expected_contribution'] ?? 0.0;
        $expectedRepayment = $row['expected_repayment'] ?? 0.0;
        $realizedContribution = $row['realized_contribution'] ?? 0.0;
        $realizedRepayment = $row['realized_repayment'] ?? 0.0;
        $shortfall = $row['shortfall'] ?? 0.0;

        $expectedTotal = $expectedContribution + $expectedRepayment;
        $realizedTotal = $realizedContribution + $realizedRepayment;

        $nextLoan = $member->loansQuery()
            ->where('status', 'active')
            ->whereNotNull('next_payment_date')
            ->orderBy('next_payment_date')
            ->first();

        $nextPaymentDate = $nextLoan?->next_payment_date;
        $nextPaymentAmount = $nextLoan
            ? (float) ($nextLoan->installment_amount ?? $nextLoan->monthly_payment)
            : null;

        return [
            'bank_balance' => (float) $member->bank_account_balance,
            'fund_balance' => (float) $member->fund_account_balance,
            'outstanding_loans' => (float) $member->outstanding_loans,
            'next_payment_date' => $nextPaymentDate,
            'next_payment_amount' => $nextPaymentAmount,
            'shortfall' => (float) $shortfall,
            'expected_total' => (float) $expectedTotal,
            'realized_total' => (float) $realizedTotal,
            'period_label' => $periodLabel,
        ];
    }

    /**
     * Recent transactions for the logged-in member.
     *
     * @return \Illuminate\Support\Collection<int, Transaction>
     */
    public function getRecentTransactions(): Collection
    {
        $member = $this->getMember();
        if (!$member) {
            return collect();
        }

        return Transaction::query()
            ->where('user_id', $member->user_id)
            ->orderByDesc('transaction_date')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();
    }

    /**
     * Quick navigation actions in the member dashboard header.
     *
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('accounts')
                ->label('View accounts')
                ->icon('heroicon-o-banknotes')
                ->color('primary')
                ->url(route('filament.member.pages.accounts')),

            Action::make('loans')
                ->label('View loans')
                ->icon('heroicon-o-banknotes')
                ->color('warning')
                ->url(route('filament.member.pages.loans')),

            Action::make('collections')
                ->label('Collections & allocations')
                ->icon('heroicon-o-calendar-days')
                ->color('info')
                ->url(route('filament.member.pages.collections')),

            Action::make('reports')
                ->label('Reports')
                ->icon('heroicon-o-document-text')
                ->color('gray')
                ->url(route('filament.member.pages.reports')),
        ];
    }

    // ─── New Widget Data Methods ──────────────────────────────────────────

    /**
     * Loan repayment progress for each active loan.
     */
    public function getLoanProgress(): array
    {
        $member = $this->getMember();
        if (!$member) {
            return [];
        }

        return $member->loansQuery()
            ->where('status', 'active')
            ->get()
            ->map(fn ($loan) => [
                'loan_id' => $loan->loan_id,
                'original_amount' => (float) $loan->original_amount,
                'total_paid' => (float) $loan->total_paid,
                'outstanding_balance' => (float) $loan->outstanding_balance,
                'progress_percent' => $loan->progress_percentage,
                'installment_amount' => (float) ($loan->installment_amount ?? $loan->monthly_payment),
                'next_payment_date' => $loan->next_payment_date,
                'remaining_term' => $loan->remaining_term,
            ])
            ->toArray();
    }

    /**
     * Fund account growth over time (monthly contribution totals).
     */
    public function getFundGrowthData(): array
    {
        $member = $this->getMember();
        if (!$member) {
            return ['labels' => [], 'data' => []];
        }

        $transactions = Transaction::query()
            ->where('user_id', $member->user_id)
            ->whereIn('type', ['contribution', 'loan_repayment'])
            ->where('status', 'complete')
            ->orderBy('transaction_date')
            ->get();

        $monthly = $transactions->groupBy(fn ($t) => $t->transaction_date->format('M Y'));
        $runningBalance = 0;
        $labels = [];
        $data = [];

        foreach ($monthly as $monthLabel => $txs) {
            $runningBalance += $txs->sum('amount');
            $labels[] = $monthLabel;
            $data[] = round($runningBalance, 2);
        }

        // Take last 12 months
        $labels = array_slice($labels, -12);
        $data = array_slice($data, -12);

        return ['labels' => $labels, 'data' => $data];
    }

    /**
     * Loan eligibility information.
     */
    public function getLoanEligibility(): array
    {
        $member = $this->getMember();
        if (!$member) {
            return ['eligible' => false, 'errors' => ['No member profile'], 'max_amount' => 0];
        }

        $errors = $member->loanEligibilityErrors();
        $hasActiveLoan = $member->hasActiveLoan();

        return [
            'eligible' => empty($errors) && !$hasActiveLoan,
            'errors' => $errors,
            'has_active_loan' => $hasActiveLoan,
            'max_amount' => $member->maxLoanAmount(),
            'fund_balance' => (float) $member->fund_account_balance,
            'min_fund_required' => Member::LOAN_MIN_FUND_BALANCE,
            'fund_progress' => min(100, round(((float) $member->fund_account_balance / Member::LOAN_MIN_FUND_BALANCE) * 100, 1)),
            'membership_years' => round($member->membershipYears(), 1),
            'min_membership_years' => Member::LOAN_MIN_MEMBERSHIP_YEARS,
        ];
    }

    /**
     * Contribution history summary.
     */
    public function getContributionHistory(): array
    {
        $member = $this->getMember();
        if (!$member) {
            return ['total_this_year' => 0, 'avg_monthly' => 0, 'streak' => 0, 'total_contributions' => 0];
        }

        $yearStart = Carbon::now()->startOfYear();

        $thisYearTotal = (float) Transaction::query()
            ->where('user_id', $member->user_id)
            ->where('type', 'contribution')
            ->where('status', 'complete')
            ->where('transaction_date', '>=', $yearStart)
            ->sum('amount');

        $monthsElapsed = max(1, Carbon::now()->month);
        $avgMonthly = $thisYearTotal / $monthsElapsed;

        // Calculate streak: consecutive months with at least one contribution
        $streak = 0;
        $checkDate = Carbon::now()->startOfMonth();
        while (true) {
            $checkDate->subMonth();
            $hasContribution = Transaction::query()
                ->where('user_id', $member->user_id)
                ->where('type', 'contribution')
                ->where('status', 'complete')
                ->whereYear('transaction_date', $checkDate->year)
                ->whereMonth('transaction_date', $checkDate->month)
                ->exists();

            if ($hasContribution) {
                $streak++;
            } else {
                break;
            }
        }

        $totalContributions = Transaction::query()
            ->where('user_id', $member->user_id)
            ->where('type', 'contribution')
            ->where('status', 'complete')
            ->count();

        return [
            'total_this_year' => $thisYearTotal,
            'avg_monthly' => round($avgMonthly, 2),
            'streak' => $streak,
            'total_contributions' => $totalContributions,
        ];
    }

    /**
     * Upcoming obligations (contributions + loan payments due).
     */
    public function getUpcomingObligations(): array
    {
        $member = $this->getMember();
        if (!$member) {
            return ['items' => [], 'total_due' => 0];
        }

        $items = [];

        // Monthly contribution
        $allocation = (int) ($member->allowed_allocation ?? 500);
        $items[] = [
            'type' => 'Contribution',
            'amount' => $allocation,
            'icon' => 'heroicon-o-arrow-up-circle',
            'color' => 'primary',
        ];

        // Active loan payments
        $activeLoans = $member->loansQuery()->where('status', 'active')->get();
        foreach ($activeLoans as $loan) {
            $items[] = [
                'type' => "Loan {$loan->loan_id}",
                'amount' => (float) ($loan->installment_amount ?? $loan->monthly_payment),
                'due_date' => $loan->next_payment_date,
                'icon' => 'heroicon-o-banknotes',
                'color' => 'warning',
            ];
        }

        $totalDue = array_sum(array_column($items, 'amount'));

        return [
            'items' => $items,
            'total_due' => $totalDue,
            'can_afford' => (float) $member->bank_account_balance >= $totalDue,
            'bank_balance' => (float) $member->bank_account_balance,
        ];
    }

    /**
     * Dependant summary (for parent members).
     */
    public function getDependantSummary(): array
    {
        $member = $this->getMember();
        if (!$member || !$member->isParentMember()) {
            return ['is_parent' => false, 'dependants' => []];
        }

        $dependants = $member->dependants()->with('user')->get();

        return [
            'is_parent' => true,
            'dependants' => $dependants->map(fn ($dep) => [
                'name' => $dep->user?->name ?? "Member #{$dep->id}",
                'bank_balance' => (float) $dep->bank_account_balance,
                'fund_balance' => (float) $dep->fund_account_balance,
            ])->toArray(),
            'total_allocated' => $dependants->sum('bank_account_balance'),
        ];
    }
}
