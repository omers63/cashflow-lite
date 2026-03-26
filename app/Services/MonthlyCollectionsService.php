<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\Member;
use App\Models\Setting;
use App\Models\Transaction;
use Carbon\Carbon;

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
     * First calendar month (start of month) that participates in collection-period assignment.
     * Configured via settings (collections_first_obligation_period, YYYY-MM); default October 2014.
     */
    public function getFirstCollectionObligationMonthStart(): Carbon
    {
        $raw = Setting::get('collections_first_obligation_period', '2014-10');
        $raw = is_string($raw) ? trim($raw) : '2014-10';
        if (preg_match('/^(\d{4})-(\d{2})$/', $raw, $m)) {
            $y = (int) $m[1];
            $mon = (int) $m[2];
            if ($mon >= 1 && $mon <= 12 && $y >= 1900 && $y <= 2100) {
                return Carbon::create($y, $mon, 1)->startOfDay();
            }
        }

        return Carbon::create(2014, 10, 1)->startOfDay();
    }

    /**
     * Assign this payment to an obligation month using per-member order:
     * if the **previous** calendar month (relative to the payment date) has not yet
     * received any allocated payment, this posting applies there; otherwise it applies
     * to the **current** calendar month’s window.
     *
     * Example: May 4 → April if April still “open”, else May. Late = after getDueDate(obligation month).
     *
     * @param  array<string, true>  $filled  Obligation months (keys Y-m) already assigned a prior posting
     * @return array{obligation_month: Carbon, obligation_label: string, due_date: Carbon, is_late: bool}|null
     */
    public function assignObligationForPaymentDate(Carbon $paymentDate, array &$filled): ?array
    {
        $p = $paymentDate->copy()->startOfDay();
        $firstStart = $this->getFirstCollectionObligationMonthStart();

        if ($p->lt($firstStart)) {
            return null;
        }

        $currentObligation = $p->copy()->startOfMonth();
        $previousObligation = $currentObligation->copy()->subMonthNoOverflow();

        $prevKey = $previousObligation->format('Y-m');
        $currKey = $currentObligation->format('Y-m');

        $previousBeforeFirstPeriod = $previousObligation->copy()->startOfMonth()->lt($firstStart);
        $previousEffectivelyFilled = isset($filled[$prevKey]) || $previousBeforeFirstPeriod;

        if (! $previousEffectivelyFilled) {
            $assigned = $previousObligation->copy();
        } else {
            $assigned = $currentObligation->copy();
        }

        if ($assigned->copy()->startOfMonth()->lt($firstStart)) {
            return null;
        }

        if (! $previousEffectivelyFilled) {
            $filled[$prevKey] = true;
        } else {
            $filled[$currKey] = true;
        }

        $due = $this->getDueDate((int) $assigned->year, (int) $assigned->month);

        return [
            'obligation_month' => $assigned->copy(),
            'obligation_label' => $assigned->format('F Y'),
            'due_date' => $due->copy(),
            'is_late' => $p->greaterThan($due),
        ];
    }

    /**
     * Persisted overrides for contribution (credit) rows: obligation month, optional due date, optional late flag.
     *
     * @param  array{obligation_month?: \DateTimeInterface|string|null, period_due_date?: \DateTimeInterface|string|null, is_late?: bool|null}|null  $input
     * @return array{collection_obligation_month: ?string, collection_period_due_date: ?string, collection_is_late: ?bool}
     */
    public function transactionAttributesForCollectionClassification(?array $input, Carbon $paymentDate): array
    {
        if ($input === null || empty($input['obligation_month'])) {
            return [
                'collection_obligation_month' => null,
                'collection_period_due_date' => null,
                'collection_is_late' => null,
            ];
        }

        $obl = Carbon::parse($input['obligation_month'])->startOfMonth();

        $due = ! empty($input['period_due_date'])
            ? Carbon::parse($input['period_due_date'])->startOfDay()
            : $this->getDueDate((int) $obl->year, (int) $obl->month);

        if (array_key_exists('is_late', $input) && $input['is_late'] !== null) {
            $isLate = (bool) $input['is_late'];
        } else {
            $isLate = $paymentDate->copy()->startOfDay()->greaterThan($due);
        }

        return [
            'collection_obligation_month' => $obl->toDateString(),
            'collection_period_due_date' => $due->toDateString(),
            'collection_is_late' => $isLate,
        ];
    }

    /**
     * One step in the member's chronological collection walk: use stored overrides or automatic assignment.
     *
     * @param  array<string, true>  $filled
     * @return array{obligation_month: Carbon, obligation_label: string, due_date: Carbon, is_late: bool}|null
     */
    public function classifyRowInMemberSequence(Transaction $row, array &$filled): ?array
    {
        $paymentDate = Carbon::parse($row->transaction_date)->startOfDay();

        if ($row->collection_obligation_month) {
            $obligationMonth = Carbon::parse($row->collection_obligation_month)->startOfMonth();
            $filled[$obligationMonth->format('Y-m')] = true;

            $due = $row->collection_period_due_date
                ? Carbon::parse($row->collection_period_due_date)->startOfDay()
                : $this->getDueDate((int) $obligationMonth->year, (int) $obligationMonth->month);

            $isLate = $row->collection_is_late !== null
                ? (bool) $row->collection_is_late
                : $paymentDate->greaterThan($due);

            return [
                'obligation_month' => $obligationMonth->copy(),
                'obligation_label' => $obligationMonth->format('F Y'),
                'due_date' => $due->copy(),
                'is_late' => $isLate,
            ];
        }

        return $this->assignObligationForPaymentDate($paymentDate, $filled);
    }

    /**
     * Classify a completed contribution or loan repayment using chronological allocation for its member.
     *
     * @return array{obligation_month: Carbon, obligation_label: string, due_date: Carbon, is_late: bool}|null
     */
    public function classifyCollectionTransaction(Transaction $transaction): ?array
    {
        if (! in_array($transaction->type, ['contribution', 'loan_repayment'], true)) {
            return null;
        }

        $userId = (int) $transaction->user_id;
        if ($userId <= 0) {
            return null;
        }

        if ($transaction->status !== 'complete') {
            return null;
        }

        $filled = [];
        /** @var \Illuminate\Database\Eloquent\Collection<int, Transaction> $rows */
        $rows = Transaction::query()
            ->where('user_id', $userId)
            ->whereIn('type', ['contribution', 'loan_repayment'])
            ->where('status', 'complete')
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get();

        foreach ($rows as $row) {
            $assignment = $this->classifyRowInMemberSequence($row, $filled);
            if ((int) $row->id === (int) $transaction->id) {
                return $assignment;
            }
        }

        return null;
    }

    /**
     * Sum of completed contribution and loan_repayment credit amounts classified as late
     * (same sequential assignment rules as classifyCollectionTransaction).
     */
    public function sumLateCollectionAmountForUser(int $userId): float
    {
        if ($userId <= 0) {
            return 0.0;
        }

        $total = 0.0;
        $filled = [];

        Transaction::query()
            ->where('user_id', $userId)
            ->whereIn('type', ['contribution', 'loan_repayment'])
            ->where('status', 'complete')
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->cursor()
            ->each(function (Transaction $transaction) use (&$total, &$filled): void {
                $classification = $this->classifyRowInMemberSequence($transaction, $filled);
                if ($classification !== null && $classification['is_late']) {
                    $total += (float) $transaction->amount;
                }
            });

        return round($total, 2);
    }

    /**
     * Count completed contribution postings classified as late vs on time for the member.
     * Uses the same sequential assignment as classifyCollectionTransaction (repayments consume slots;
     * only rows with type contribution increment these counts).
     *
     * @return array{late: int, on_time: int}
     */
    public function countContributionsByTimingForUser(int $userId): array
    {
        if ($userId <= 0) {
            return ['late' => 0, 'on_time' => 0];
        }

        $late = 0;
        $onTime = 0;
        $filled = [];

        Transaction::query()
            ->where('user_id', $userId)
            ->whereIn('type', ['contribution', 'loan_repayment'])
            ->where('status', 'complete')
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->cursor()
            ->each(function (Transaction $transaction) use (&$late, &$onTime, &$filled): void {
                $classification = $this->classifyRowInMemberSequence($transaction, $filled);
                if ($transaction->type !== 'contribution') {
                    return;
                }
                if ($classification === null) {
                    return;
                }
                if ($classification['is_late']) {
                    $late++;
                } else {
                    $onTime++;
                }
            });

        return ['late' => $late, 'on_time' => $onTime];
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

        $members = Member::with(['user'])
            ->whereHas('user', fn ($q) => $q->where('status', 'active'))
            ->get();

        $out = [];
        foreach ($members as $member) {
            if (! $member instanceof Member) {
                continue;
            }
            $expectedContribution = (float) ($member->allowed_allocation ?? Setting::getInt('default_allocation', 500));
            $expectedRepayment = $member->loansQuery()
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
            if (! $parent) {
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
                $parent->allocateToDependant($dependant, $amount, "Monthly allocation for {$year}-".str_pad((string) $month, 2, '0', STR_PAD_LEFT), $dueDate);
                $processed++;
            } catch (\Throwable $e) {
                $errors[] = "Allocation failed for dependant {$dependant->id}: ".$e->getMessage();
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
                    $member->contribute(
                        $amount,
                        "Monthly contribution for {$year}-".str_pad((string) $month, 2, '0', STR_PAD_LEFT),
                        $dueDate,
                        [
                            'obligation_month' => Carbon::create($year, $month, 1)->startOfMonth(),
                            'period_due_date' => $dueDate,
                            'is_late' => false,
                        ],
                    );
                    $contributions++;
                    $member->refresh();
                } catch (\Throwable $e) {
                    $errors[] = "Contribution failed for member {$member->id}: ".$e->getMessage();
                }
            }

            $activeLoanIds = $member->loansQuery()->where('status', 'active')->pluck('id');
            /** @var \Illuminate\Database\Eloquent\Collection<int, Loan> $activeLoans */
            $activeLoans = Loan::query()->whereIn('id', $activeLoanIds)->get();

            foreach ($activeLoans as $loan) {
                if (! $loan instanceof Loan) {
                    continue;
                }
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
                    $errors[] = "Loan repayment failed for member {$member->id}, loan {$loan->loan_id}: ".$e->getMessage();
                }
            }
        }

        return ['contributions' => $contributions, 'repayments' => $repayments, 'skipped_insufficient' => $skippedInsufficient, 'errors' => $errors];
    }
}
