<?php

namespace App\Services;

use App\Models\Member;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\Loan;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class MonthlyCollectionsService
{
    /** Due day of the following month (e.g. 5 = 5th of next month). */
    public const DUE_DAY = 5;

    public function getDueDay(): int
    {
        $day = Setting::getInt('collections_due_day', self::DUE_DAY);

        return max(1, min(28, $day));
    }

    /**
     * Period start and end for a given month (full calendar month).
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function getPeriodStartEnd(int $year, int $month): array
    {
        $start = Carbon::create($year, $month, 1)->startOfDay();
        $end = Carbon::create($year, $month, 1)->endOfMonth();

        return [$start, $end];
    }

    /**
     * Due date for the period: 5th of the following month.
     */
    public function getDueDate(int $year, int $month): Carbon
    {
        $next = Carbon::create($year, $month, 1)->addMonth();

        return $next->setDay($this->getDueDay())->startOfDay();
    }

    /**
     * Members with unrealized collections for the period (expected contribution + loan repayment vs realized).
     * Excludes members with no user (inactive). Includes both parents and dependants.
     *
     * @return array<int, array{member: Member, expected_contribution: float, expected_repayment: float, realized_contribution: float, realized_repayment: float, shortfall: float}>
     */
    public function getUnrealizedMembers(int $year, int $month): array
    {
        // Collections for a period (e.g. December) are actually dated on the due
        // date in the following month (e.g. 5th January). To correctly mark
        // collections as realized once they are run, we look at contribution and
        // repayment transactions in the due-date month, not the original period month.
        $dueDate = $this->getDueDate($year, $month);
        [$start, $end] = $this->getPeriodStartEnd((int) $dueDate->format('Y'), (int) $dueDate->format('n'));

        $members = Member::with(['user', 'loans'])
            ->whereHas('user', fn ($q) => $q->where('status', 'active'))
            ->get();

        $out = [];
        foreach ($members as $member) {
            if (!$member instanceof Member) {
                continue;
            }
            $expectedContribution = (float) ($member->allowed_allocation ?? Setting::getInt('default_allocation', 500));
            $expectedRepayment = $member->loans()
                ->where('status', 'active')
                ->get()
                ->sum(fn (Loan $loan) => min(
                    (float) ($loan->installment_amount ?? $loan->monthly_payment),
                    (float) $loan->outstanding_balance
                ));

            $realizedContribution = (float) Transaction::where('user_id', $member->user_id)
                ->where('type', 'contribution')
                ->whereBetween('transaction_date', [$start, $end])
                ->sum('amount');

            $realizedRepayment = (float) Transaction::where('user_id', $member->user_id)
                ->where('type', 'loan_repayment')
                ->whereBetween('transaction_date', [$start, $end])
                ->sum('amount');

            $expectedTotal = $expectedContribution + $expectedRepayment;
            $realizedTotal = $realizedContribution + $realizedRepayment;
            $shortfall = max(0, $expectedTotal - $realizedTotal);

            if ($shortfall > 0.001) {
                $out[$member->id] = [
                    'member' => $member,
                    'expected_contribution' => $expectedContribution,
                    'expected_repayment' => $expectedRepayment,
                    'realized_contribution' => $realizedContribution,
                    'realized_repayment' => $realizedRepayment,
                    'shortfall' => $shortfall,
                ];
            }
        }

        return $out;
    }

    /**
     * Dependants who did not receive an allocation_from_parent in the period.
     *
     * @return array<int, array{dependant: Member, parent: Member, allowed_allocation: float}>
     */
    public function getUnallocatedDependants(int $year, int $month): array
    {
        // For a given period (e.g. December), allocations are dated at the due date
        // in the following month (e.g. 5th January). To avoid showing dependants as
        // still unallocated after running allocations, we look for allocation
        // transactions in the due-date month, not the original period month.
        $dueDate = $this->getDueDate($year, $month);
        [$start, $end] = $this->getPeriodStartEnd((int) $dueDate->format('Y'), (int) $dueDate->format('n'));

        $dependants = Member::with(['user', 'parent.user'])
            ->whereNotNull('parent_id')
            ->whereHas('user', fn ($q) => $q->where('status', 'active'))
            ->get();

        $allocatedUserIds = Transaction::where('type', 'allocation_from_parent')
            ->whereBetween('transaction_date', [$start, $end])
            ->pluck('user_id')
            ->unique()
            ->filter()
            ->all();

        $out = [];
        foreach ($dependants as $dependant) {
            if (in_array((int) $dependant->user_id, $allocatedUserIds, true)) {
                continue;
            }
            $parent = $dependant->parent;
            if (!$parent) {
                continue;
            }
            $out[$dependant->id] = [
                'dependant' => $dependant,
                'parent' => $parent,
                'allowed_allocation' => (float) ($dependant->allowed_allocation ?? Setting::getInt('default_allocation', 500)),
            ];
        }

        return $out;
    }

    /**
     * Run allocations for all parent–dependant pairs that have not been allocated in the period.
     * Uses the due date (5th of next month) as the transaction date. Runs only if parent has sufficient balance.
     *
     * @return array{processed: int, skipped_insufficient: int, errors: string[]}
     */
    public function runAllocationsForPeriod(int $year, int $month): array
    {
        $unallocated = $this->getUnallocatedDependants($year, $month);
        $dueDate = $this->getDueDate($year, $month);
        $processed = 0;
        $skippedInsufficient = 0;
        $errors = [];

        foreach ($unallocated as $row) {
            $parent = $row['parent'];
            $dependant = $row['dependant'];
            $amount = $row['allowed_allocation'];

            if ((float) $parent->bank_account_balance < $amount) {
                $skippedInsufficient++;
                $errors[] = "Parent {$parent->user?->name} (ID {$parent->id}) has insufficient balance for dependant {$dependant->user?->name}.";
                continue;
            }

            try {
                $parent->allocateToDependant($dependant, $amount, "Monthly allocation for {$year}-" . str_pad((string) $month, 2, '0', STR_PAD_LEFT), $dueDate);
                $processed++;
            } catch (\Throwable $e) {
                $errors[] = "Allocation failed for dependant {$dependant->id}: " . $e->getMessage();
            }
        }

        return ['processed' => $processed, 'skipped_insufficient' => $skippedInsufficient, 'errors' => $errors];
    }

    /**
     * Run contributions and loan repayments for all members with shortfall for the period.
     * Uses the due date (5th of next month) as the transaction date. Applies contribution first, then loan repayments.
     *
     * @return array{contributions: int, repayments: int, skipped_insufficient: int, errors: string[]}
     */
    public function runContributionsAndRepaymentsForPeriod(int $year, int $month): array
    {
        $unrealized = $this->getUnrealizedMembers($year, $month);
        $dueDate = $this->getDueDate($year, $month);
        $contributions = 0;
        $repayments = 0;
        $skippedInsufficient = 0;
        $errors = [];

        foreach ($unrealized as $row) {
            $member = $row['member'];
            $member->refresh();

            $expectedContribution = $row['expected_contribution'];
            $realizedContribution = $row['realized_contribution'];
            $contributionShortfall = max(0, $expectedContribution - $realizedContribution);

            if ($contributionShortfall > 0.001) {
                $amount = $contributionShortfall;
                if ((float) $member->bank_account_balance < $amount) {
                    $skippedInsufficient++;
                    $errors[] = "{$member->user?->name} (ID {$member->id}): insufficient balance for contribution (\${$amount}).";
                    continue;
                }
                try {
                    $member->contribute($amount, "Monthly contribution for {$year}-" . str_pad((string) $month, 2, '0', STR_PAD_LEFT), $dueDate);
                    $contributions++;
                    $member->refresh();
                } catch (\Throwable $e) {
                    $errors[] = "Contribution failed for member {$member->id}: " . $e->getMessage();
                }
            }

            $activeLoans = $member->loans()->where('status', 'active')->get();
            foreach ($activeLoans as $loan) {
                $installment = min(
                    (float) ($loan->installment_amount ?? $loan->monthly_payment),
                    (float) $loan->outstanding_balance
                );
                if ($installment <= 0.001) {
                    continue;
                }
                $realizedForThisLoan = (float) Transaction::where('user_id', $member->user_id)
                    ->where('type', 'loan_repayment')
                    ->where('reference', $loan->loan_id)
                    ->whereBetween('transaction_date', $this->getPeriodStartEnd($year, $month))
                    ->sum('amount');
                if ($realizedForThisLoan >= $installment - 0.01) {
                    continue;
                }
                $member->refresh();
                if ((float) $member->bank_account_balance < $installment) {
                    $skippedInsufficient++;
                    $errors[] = "{$member->user?->name} (ID {$member->id}): insufficient balance for loan {$loan->loan_id} repayment.";
                    continue;
                }
                try {
                    $member->makeRepayment($loan, $dueDate);
                    $repayments++;
                    $member->refresh();
                } catch (\Throwable $e) {
                    $errors[] = "Loan repayment failed for member {$member->id}, loan {$loan->loan_id}: " . $e->getMessage();
                }
            }
        }

        return ['contributions' => $contributions, 'repayments' => $repayments, 'skipped_insufficient' => $skippedInsufficient, 'errors' => $errors];
    }
}
