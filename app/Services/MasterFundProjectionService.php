<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\MasterAccount;
use App\Models\Member;
use App\Models\Setting;
use Carbon\Carbon;

class MasterFundProjectionService
{
    /**
     * Projected master fund balance: current balance + projected contributions and loan repayments
     * for the period (if run) minus pending loan disbursements from the loan queue.
     *
     * @return array{
     *   current_balance: float,
     *   projected_contributions: float,
     *   projected_repayments: float,
     *   pending_disbursements: float,
     *   loan_queue_count: int,
     *   projected_balance: float,
     *   loan_queue: \Illuminate\Support\Collection
     * }
     */
    public function getProjection(int $year, int $month): array
    {
        $masterFund = MasterAccount::getMasterFund();
        $currentBalance = (float) $masterFund->balance;

        $collectionsService = app(MonthlyCollectionsService::class);
        [$start, $end] = $collectionsService->getPeriodStartEnd($year, $month);

        $members = Member::with(['user'])
            ->whereHas('user', fn ($q) => $q->where('status', 'active'))
            ->get();

        $projectedContributions = 0.0;
        $projectedRepayments = 0.0;

        foreach ($members as $member) {
            if (!$member instanceof Member) {
                continue;
            }
            $projectedContributions += (float) ($member->allowed_allocation ?? Setting::getInt('default_allocation', 500));
            $projectedRepayments += $member->loansQuery()
                ->where('status', 'active')
                ->get()
                ->sum(fn (Loan $loan) => min(
                    (float) ($loan->installment_amount ?? $loan->monthly_payment),
                    (float) $loan->outstanding_balance
                ));
        }

        $loanQueue = Loan::loanQueue()->with(['user', 'member'])->get();
        $pendingDisbursements = $loanQueue->sum(fn (Loan $loan) => (float) $loan->original_amount);

        $projectedBalance = $currentBalance + $projectedContributions + $projectedRepayments - $pendingDisbursements;

        return [
            'current_balance' => $currentBalance,
            'projected_contributions' => $projectedContributions,
            'projected_repayments' => $projectedRepayments,
            'pending_disbursements' => $pendingDisbursements,
            'loan_queue_count' => $loanQueue->count(),
            'projected_balance' => $projectedBalance,
            'loan_queue' => $loanQueue,
            'period_label' => Carbon::create($year, $month, 1)->format('F Y'),
        ];
    }

    /**
     * Loan queue (pending loans) ordered by tier and submission date.
     */
    public function getLoanQueue(): \Illuminate\Database\Eloquent\Collection
    {
        return Loan::loanQueue()->with(['user', 'member'])->get();
    }

    /**
     * Project month-by-month until master fund reaches (or exceeds) the target amount.
     * First period applies current loan queue disbursements; subsequent periods add only
     * contributions and repayments (same monthly amounts as first period).
     *
     * @param  float  $targetAmount  Target master fund balance to reach
     * @param  int  $maxMonths  Maximum months to project (default 60)
     * @return array{
     *   reached: bool,
     *   target_amount: float,
     *   current_balance: float,
     *   reach_year: int|null,
     *   reach_month: int|null,
     *   reach_period_label: string|null,
     *   balance_at_reach: float|null,
     *   months_to_reach: int|null,
     *   total_contributions: float,
     *   total_repayments: float,
     *   total_disbursements: float,
     *   final_balance: float,
     *   months_projected: int,
     *   monthly_contributions: float,
     *   monthly_repayments: float,
     *   first_period_disbursements: float
     * }
     */
    public function getTargetReachProjection(float $targetAmount, int $maxMonths = 60): array
    {
        $masterFund = MasterAccount::getMasterFund();
        $currentBalance = (float) $masterFund->balance;

        if ($targetAmount <= $currentBalance) {
            $period = Carbon::now();
            return [
                'reached' => true,
                'target_amount' => $targetAmount,
                'current_balance' => $currentBalance,
                'reach_year' => (int) $period->format('Y'),
                'reach_month' => (int) $period->format('n'),
                'reach_period_label' => $period->format('F Y'),
                'balance_at_reach' => $currentBalance,
                'months_to_reach' => 0,
                'total_contributions' => 0.0,
                'total_repayments' => 0.0,
                'total_disbursements' => 0.0,
                'final_balance' => $currentBalance,
                'months_projected' => 0,
                'monthly_contributions' => 0.0,
                'monthly_repayments' => 0.0,
                'first_period_disbursements' => 0.0,
            ];
        }

        $year = (int) Carbon::now()->format('Y');
        $month = (int) Carbon::now()->format('n');
        $first = $this->getProjection($year, $month);

        $monthlyContributions = $first['projected_contributions'];
        $monthlyRepayments = $first['projected_repayments'];
        $firstPeriodDisbursements = $first['pending_disbursements'];

        $balance = $currentBalance;
        $totalContributions = 0.0;
        $totalRepayments = 0.0;
        $totalDisbursements = 0.0;
        $reached = false;
        $reachYear = null;
        $reachMonth = null;
        $balanceAtReach = null;
        $monthsToReach = null;
        $reachPeriodLabel = null;

        for ($i = 0; $i < $maxMonths; $i++) {
            $period = Carbon::create($year, $month, 1);
            $contributions = $monthlyContributions;
            $repayments = $monthlyRepayments;
            $disbursements = $i === 0 ? $firstPeriodDisbursements : 0.0;

            $balance += $contributions + $repayments - $disbursements;
            $totalContributions += $contributions;
            $totalRepayments += $repayments;
            $totalDisbursements += $disbursements;

            if ($balance >= $targetAmount) {
                $reached = true;
                $reachYear = (int) $period->format('Y');
                $reachMonth = (int) $period->format('n');
                $reachPeriodLabel = $period->format('F Y');
                $balanceAtReach = $balance;
                $monthsToReach = $i + 1;
                break;
            }

            $period->addMonth();
            $year = (int) $period->format('Y');
            $month = (int) $period->format('n');
        }

        return [
            'reached' => $reached,
            'target_amount' => $targetAmount,
            'current_balance' => $currentBalance,
            'reach_year' => $reachYear,
            'reach_month' => $reachMonth,
            'reach_period_label' => $reachPeriodLabel,
            'balance_at_reach' => $balanceAtReach,
            'months_to_reach' => $monthsToReach,
            'total_contributions' => $totalContributions,
            'total_repayments' => $totalRepayments,
            'total_disbursements' => $totalDisbursements,
            'final_balance' => $balance,
            'months_projected' => min($maxMonths, $monthsToReach ?? $maxMonths),
            'monthly_contributions' => $monthlyContributions,
            'monthly_repayments' => $monthlyRepayments,
            'first_period_disbursements' => $firstPeriodDisbursements,
        ];
    }
}
