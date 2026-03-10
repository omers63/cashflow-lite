<?php

namespace App\Filament\Member\Pages;

use App\Models\Member;
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

        $nextLoan = $member->loans()
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
}

