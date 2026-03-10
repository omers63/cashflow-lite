<?php

namespace App\Filament\Member\Pages;

use App\Models\Member;
use App\Models\Transaction;
use Carbon\Carbon;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

class Reports extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'Reports';
    protected static string|\UnitEnum|null $navigationGroup = 'Documents & Reports';
    protected static ?int $navigationSort = 4;
    protected string $view = 'filament.member.pages.reports';

    public int $year;
    public int $month;

    public function mount(): void
    {
        $now = Carbon::now()->subMonth();
        $this->year = (int) $now->format('Y');
        $this->month = (int) $now->format('n');
    }

    public function getTitle(): string|Htmlable
    {
        return 'Reports';
    }

    public function getMember(): ?Member
    {
        return auth()->user()?->member;
    }

    public function getPeriodLabel(): string
    {
        return Carbon::create($this->year, $this->month, 1)->format('F Y');
    }

    public function getStatementSummary(): array
    {
        $member = $this->getMember();
        if (!$member) {
            return [
                'opening_bank' => 0.0,
                'closing_bank' => 0.0,
                'opening_fund' => 0.0,
                'closing_fund' => 0.0,
                'contributions' => 0.0,
                'repayments' => 0.0,
            ];
        }

        $start = Carbon::create($this->year, $this->month, 1)->startOfDay();
        $end = Carbon::create($this->year, $this->month, 1)->endOfMonth();

        // Simple approximation: treat current balances as closing; no historical balance recomputation here.
        $closingBank = (float) $member->bank_account_balance;
        $closingFund = (float) $member->fund_account_balance;

        $contribTotal = (float) Transaction::where('user_id', $member->user_id)
            ->where('type', 'contribution')
            ->whereBetween('transaction_date', [$start, $end])
            ->sum('amount');

        $repayTotal = (float) Transaction::where('user_id', $member->user_id)
            ->where('type', 'loan_repayment')
            ->whereBetween('transaction_date', [$start, $end])
            ->sum('amount');

        return [
            'opening_bank' => 0.0,
            'closing_bank' => $closingBank,
            'opening_fund' => 0.0,
            'closing_fund' => $closingFund,
            'contributions' => $contribTotal,
            'repayments' => $repayTotal,
        ];
    }
}

