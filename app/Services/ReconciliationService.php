<?php

namespace App\Services;

use App\Models\MasterAccount;
use App\Models\Member;
use App\Models\User;
use App\Models\Loan;
use App\Models\Transaction;
use App\Models\ExternalBankAccount;
use App\Models\ExternalBankImport;
use App\Models\Reconciliation;
use App\Models\Exception;
use App\Models\BalanceSnapshot;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReconciliationService
{
    protected array $checks = [];
    protected float $tolerance = 0.01;

    /**
     * Run daily reconciliation with all recommended checks (E1, E2, M1, M2, MEM1, MEM2, MEM3, L1, L2).
     */
    public function runDailyReconciliation(): Reconciliation
    {
        return $this->runReconciliation(now(), 'daily');
    }

    /**
     * Run monthly reconciliation for the given period (default: previous month).
     * Same checks as daily; reconciliation_date is the last day of the period.
     */
    public function runMonthlyReconciliation(?\DateTimeInterface $periodEnd = null): Reconciliation
    {
        $end = $periodEnd
            ? Carbon::parse($periodEnd)
            : now()->subMonth()->endOfMonth();
        return $this->runReconciliation($end, 'monthly');
    }

    /**
     * Run all checks and create a reconciliation record.
     */
    protected function runReconciliation(\DateTimeInterface $reconciliationDate, string $type): Reconciliation
    {
        $this->checks = [];

        $this->checkE1_ExternalImportsVsBalance();
        $this->checkE2_ExternalBanksVsMasterBank();
        $this->checkM1_MasterBankVsRecomputed();
        $this->checkM2_MasterFundVsMembersAndLoans();
        $this->checkM3_MasterFundVsRecomputed();
        $this->checkMEM1_MemberBankVsRecomputed();
        $this->checkMEM2_MemberFundVsRecomputed();
        $this->checkMEM3_NegativeBalances();
        $this->checkL1_LoanOutstandingVsPayments();
        $this->checkL2_LoanScheduleVsActual();

        $allPassed = collect($this->checks)->every(fn($c) => ($c['status'] ?? '') === 'PASS');
        $checksPassed = collect($this->checks)->where('status', 'PASS')->count();
        $checksFailed = collect($this->checks)->where('status', 'FAIL')->count();
        $totalVariance = collect($this->checks)->sum(fn($c) => abs($c['variance'] ?? 0));

        $reconciliation = Reconciliation::create([
            'reconciliation_date' => Carbon::parse($reconciliationDate)->toDateString(),
            'type' => $type,
            'check_results' => $this->checks,
            'all_passed' => $allPassed,
            'checks_passed' => $checksPassed,
            'checks_failed' => $checksFailed,
            'total_variance' => $totalVariance,
            'status' => $allPassed ? 'complete' : 'failed',
            'performed_by' => auth()->id(),
        ]);

        if (!$allPassed) {
            $this->createExceptionsForFailedChecks($reconciliation);
        }

        return $reconciliation;
    }

    /**
     * E1: For each external bank, stored current_balance = sum(imports posted to master).
     */
    protected function checkE1_ExternalImportsVsBalance(): void
    {
        $banks = ExternalBankAccount::active()->get();
        $failedBanks = [];
        $totalVariance = 0.0;

        /** @var \App\Models\ExternalBankAccount $bank */
        foreach ($banks as $bank) {
            $sumImports = (float) $bank->imports()->where('imported_to_master', true)->sum('amount');
            $stored = (float) $bank->current_balance;
            $variance = $stored - $sumImports;
            if (abs($variance) >= $this->tolerance) {
                $failedBanks[] = ['id' => $bank->id, 'name' => $bank->bank_name, 'variance' => $variance];
                $totalVariance += abs($variance);
            }
        }

        $status = empty($failedBanks) ? 'PASS' : 'FAIL';
        $this->checks[] = [
            'key' => 'E1',
            'name' => 'External imports vs balance',
            'description' => 'Per external bank: stored balance = sum of imports posted to master',
            'expected' => null,
            'actual' => null,
            'variance' => $totalVariance,
            'status' => $status,
            'details' => $failedBanks ?: null,
        ];
    }

    /**
     * E2: Sum of external bank balances = Master Bank balance.
     */
    protected function checkE2_ExternalBanksVsMasterBank(): void
    {
        $masterBank = MasterAccount::getMasterBank();
        $externalTotal = (float) ExternalBankAccount::active()->sum('current_balance');
        $variance = (float) $masterBank->balance - $externalTotal;
        $status = abs($variance) < $this->tolerance ? 'PASS' : 'FAIL';

        $this->checks[] = [
            'key' => 'E2',
            'name' => 'External banks vs Master Bank',
            'description' => 'Sum of external bank balances = Master Bank',
            'expected' => $externalTotal,
            'actual' => (float) $masterBank->balance,
            'variance' => $variance,
            'status' => $status,
        ];
    }

    /**
     * M1: Master Bank stored balance vs recomputed from transactions.
     * Per financial treatments: external_import credits Master Bank; master_to_user_bank debits it.
     * Loan disbursement debits Master Fund (not Master Bank), so it is excluded here.
     */
    protected function checkM1_MasterBankVsRecomputed(): void
    {
        $masterBank = MasterAccount::getMasterBank();
        $credits = (float) Transaction::where('type', 'external_import')->sum('amount');
        $debits = (float) Transaction::where('type', 'master_to_user_bank')->sum('amount');
        $recomputed = $credits - $debits;
        $stored = (float) $masterBank->balance;
        $variance = $stored - $recomputed;
        $status = abs($variance) < $this->tolerance ? 'PASS' : 'FAIL';

        $this->checks[] = [
            'key' => 'M1',
            'name' => 'Master Bank vs recomputed',
            'description' => 'Master Bank = external_import (credits) − master_to_user_bank (debits). Loan disbursement affects Master Fund.',
            'expected' => $recomputed,
            'actual' => $stored,
            'variance' => $variance,
            'status' => $status,
        ];
    }

    /**
     * M2: Master Fund = Σ(member fund balances) − Σ(outstanding loan balances).
     * Per financial treatments: Master Fund is credited by contribution and loan_repayment,
     * debited by loan_disbursement.
     */
    protected function checkM2_MasterFundVsMembersAndLoans(): void
    {
        $masterFund = MasterAccount::getMasterFund();
        $memberFundsTotal = (float) Member::whereHas('user', fn($q) => $q->where('status', 'active'))->sum('fund_account_balance');
        $outstandingLoans = (float) Loan::active()->sum('outstanding_balance');
        $expected = $memberFundsTotal - $outstandingLoans;
        $stored = (float) $masterFund->balance;
        $variance = $stored - $expected;
        $status = abs($variance) < $this->tolerance ? 'PASS' : 'FAIL';

        $this->checks[] = [
            'key' => 'M2',
            'name' => 'Master Fund vs members & loans',
            'description' => 'Master Fund = Σ(member funds) − Σ(outstanding loans)',
            'expected' => $expected,
            'actual' => $stored,
            'variance' => $variance,
            'status' => $status,
        ];
    }

    /**
     * M3: Master Fund stored balance vs recomputed from transactions.
     * Per financial treatments: credits = contribution, loan_repayment; debits = loan_disbursement.
     */
    protected function checkM3_MasterFundVsRecomputed(): void
    {
        $masterFund = MasterAccount::getMasterFund();
        $credits = (float) Transaction::whereIn('type', ['contribution', 'loan_repayment'])->sum('amount');
        $debits = (float) Transaction::where('type', 'loan_disbursement')->sum('amount');
        $recomputed = $credits - $debits;
        $stored = (float) $masterFund->balance;
        $variance = $stored - $recomputed;
        $status = abs($variance) < $this->tolerance ? 'PASS' : 'FAIL';

        $this->checks[] = [
            'key' => 'M3',
            'name' => 'Master Fund vs recomputed',
            'description' => 'Master Fund = contribution + loan_repayment (credits) − loan_disbursement (debits)',
            'expected' => $recomputed,
            'actual' => $stored,
            'variance' => $variance,
            'status' => $status,
        ];
    }

    /**
     * MEM1: Each active member's bank_account_balance = recomputed from transactions.
     */
    protected function checkMEM1_MemberBankVsRecomputed(): void
    {
        $members = Member::with('user')->whereHas('user', fn($q) => $q->where('status', 'active'))->get();
        $mismatches = [];
        $totalVariance = 0.0;

        /** @var \App\Models\Member $member */
        foreach ($members as $member) {
            $computed = $member->computeBankAccountBalanceFromTransactions();
            $stored = (float) $member->bank_account_balance;
            $variance = $stored - $computed;
            if (abs($variance) >= $this->tolerance) {
                $mismatches[] = ['member_id' => $member->id, 'stored' => $stored, 'computed' => $computed, 'variance' => $variance];
                $totalVariance += abs($variance);
            }
        }

        $status = empty($mismatches) ? 'PASS' : 'FAIL';
        $this->checks[] = [
            'key' => 'MEM1',
            'name' => 'Member bank vs recomputed',
            'description' => 'Each member bank balance = recomputed from transactions',
            'expected' => null,
            'actual' => null,
            'variance' => $totalVariance,
            'status' => $status,
            'details' => $mismatches ?: null,
        ];
    }

    /**
     * MEM2: Each active member's fund_account_balance = recomputed from transactions.
     */
    protected function checkMEM2_MemberFundVsRecomputed(): void
    {
        $members = Member::with('user')->whereHas('user', fn($q) => $q->where('status', 'active'))->get();
        $mismatches = [];
        $totalVariance = 0.0;

        /** @var \App\Models\Member $member */
        foreach ($members as $member) {
            $computed = $member->computeFundAccountBalanceFromTransactions();
            $stored = (float) $member->fund_account_balance;
            $variance = $stored - $computed;
            if (abs($variance) >= $this->tolerance) {
                $mismatches[] = ['member_id' => $member->id, 'stored' => $stored, 'computed' => $computed, 'variance' => $variance];
                $totalVariance += abs($variance);
            }
        }

        $status = empty($mismatches) ? 'PASS' : 'FAIL';
        $this->checks[] = [
            'key' => 'MEM2',
            'name' => 'Member fund vs recomputed',
            'description' => 'Each member fund balance = recomputed from transactions',
            'expected' => null,
            'actual' => null,
            'variance' => $totalVariance,
            'status' => $status,
            'details' => $mismatches ?: null,
        ];
    }

    /**
     * MEM3: No member (bank or fund) should have negative balance (business rule: disallow unless exception).
     */
    protected function checkMEM3_NegativeBalances(): void
    {
        $members = Member::with('user')->whereHas('user', fn($q) => $q->where('status', 'active'))->get();
        $negative = [];

        /** @var \App\Models\Member $member */
        foreach ($members as $member) {
            $bank = (float) $member->bank_account_balance;
            $fund = (float) $member->fund_account_balance;
            if ($bank < -$this->tolerance || $fund < -$this->tolerance) {
                $negative[] = [
                    'member_id' => $member->id,
                    'bank_balance' => $bank,
                    'fund_balance' => $fund,
                ];
            }
        }

        $status = empty($negative) ? 'PASS' : 'FAIL';
        $this->checks[] = [
            'key' => 'MEM3',
            'name' => 'Negative balance check',
            'description' => 'No member bank or fund balance may be negative',
            'expected' => null,
            'actual' => null,
            'variance' => count($negative),
            'status' => $status,
            'details' => $negative ?: null,
            'exception_type' => 'negative_balance',
        ];
    }

    /**
     * L1: Each active loan's outstanding_balance = recomputed from payment history.
     */
    protected function checkL1_LoanOutstandingVsPayments(): void
    {
        $loans = Loan::active()->get();
        $mismatches = [];
        $totalVariance = 0.0;

        /** @var \App\Models\Loan $loan */
        foreach ($loans as $loan) {
            $recomputed = $loan->recomputeOutstandingBalanceFromPayments();
            $stored = (float) $loan->outstanding_balance;
            $variance = $stored - $recomputed;
            if (abs($variance) >= $this->tolerance) {
                $mismatches[] = [
                    'loan_id' => $loan->id,
                    'loan_ref' => $loan->loan_id,
                    'stored' => $stored,
                    'computed' => $recomputed,
                    'variance' => $variance,
                ];
                $totalVariance += abs($variance);
            }
        }

        $status = empty($mismatches) ? 'PASS' : 'FAIL';
        $this->checks[] = [
            'key' => 'L1',
            'name' => 'Loan outstanding vs payments',
            'description' => 'Each loan outstanding balance = recomputed from payment history',
            'expected' => null,
            'actual' => null,
            'variance' => $totalVariance,
            'status' => $status,
            'details' => $mismatches ?: null,
            'exception_type' => 'loan_payment_mismatch',
        ];
    }

    /**
     * L2: Loan schedule vs actual — delinquent loans (past next_payment_date) and missed/partial payments.
     */
    protected function checkL2_LoanScheduleVsActual(): void
    {
        $loans = Loan::active()->get();
        $delinquent = [];
        $totalDaysOverdue = 0;

        /** @var \App\Models\Loan $loan */
        foreach ($loans as $loan) {
            if (!$loan->isDelinquent()) {
                continue;
            }
            $daysOverdue = (int) $loan->days_overdue;
            $delinquent[] = [
                'loan_id' => $loan->id,
                'loan_ref' => $loan->loan_id,
                'next_payment_date' => $loan->next_payment_date?->format('Y-m-d'),
                'days_overdue' => $daysOverdue,
            ];
            $totalDaysOverdue += $daysOverdue;
        }

        $status = empty($delinquent) ? 'PASS' : 'FAIL';
        $this->checks[] = [
            'key' => 'L2',
            'name' => 'Loan schedule vs actual (delinquency)',
            'description' => 'No active loan should be past next payment date',
            'expected' => null,
            'actual' => count($delinquent),
            'variance' => (float) $totalDaysOverdue,
            'status' => $status,
            'details' => $delinquent ?: null,
            'exception_type' => 'loan_delinquency',
        ];
    }

    /**
     * Create exceptions for failed checks (with type and check key for filtering).
     */
    protected function createExceptionsForFailedChecks(Reconciliation $reconciliation): void
    {
        foreach ($this->checks as $check) {
            if (($check['status'] ?? '') !== 'FAIL') {
                continue;
            }

            $variance = abs($check['variance'] ?? 0);
            $severity = match (true) {
                $variance > 1000 => 'critical',
                $variance > 100 => 'high',
                $variance > 10 => 'medium',
                default => 'low',
            };

            $type = $check['exception_type'] ?? 'balance_mismatch';
            $key = $check['key'] ?? 'unknown';
            $name = $check['name'] ?? "Check {$key}";
            $expected = $check['expected'] ?? 'N/A';
            $actual = $check['actual'] ?? 'N/A';
            $desc = "Reconciliation {$key} failed: {$name}. Expected: {$expected}, Actual: {$actual}, Variance: " . ($check['variance'] ?? 0);
            if (!empty($check['details'])) {
                $desc .= ' | Details: ' . json_encode($check['details']);
            }

            Exception::create([
                'exception_id' => Exception::generateExceptionId(),
                'type' => $type,
                'severity' => $severity,
                'description' => $desc,
                'variance_amount' => $variance,
                'related_reconciliation_id' => $reconciliation->id,
                'status' => 'open',
                'sla_hours' => Exception::getSlaHours($severity),
                'sla_deadline' => now()->addHours(Exception::getSlaHours($severity)),
                'affected_accounts' => ['reconciliation_check' => $key],
            ]);
        }
    }

    /**
     * Create a monthly balance snapshot (current system totals stored for the given period).
     * Run at period end for accurate month-end snapshot. Defaults to previous month.
     */
    public function createMonthlyBalanceSnapshot(?int $year = null, ?int $month = null): BalanceSnapshot
    {
        $date = $year !== null && $month !== null
            ? Carbon::create($year, $month)->endOfMonth()
            : now()->subMonth()->endOfMonth();
        $totals = $this->getSystemTotals();
        return BalanceSnapshot::create([
            'snapshot_date' => $date->toDateString(),
            'period' => 'monthly',
            'master_bank' => $totals['master_bank'],
            'master_fund' => $totals['master_fund'],
            'external_banks_total' => $totals['external_banks_total'],
            'member_banks_total' => $totals['user_banks_total'],
            'member_funds_total' => $totals['user_funds_total'],
            'outstanding_loans_total' => $totals['outstanding_loans_total'],
            'created_by' => auth()->id(),
        ]);
    }

    /**
     * Get reconciliation summary for dashboard.
     */
    public function getReconciliationSummary(): array
    {
        $latest = Reconciliation::orderBy('reconciliation_date', 'desc')->first();
        $thisMonth = Reconciliation::whereMonth('reconciliation_date', now()->month)
            ->whereYear('reconciliation_date', now()->year)
            ->get();

        $latestMonthly = Reconciliation::where('type', 'monthly')->orderBy('reconciliation_date', 'desc')->first();
        $latestSnapshot = BalanceSnapshot::orderBy('snapshot_date', 'desc')->first();

        return [
            'latest' => $latest,
            'latest_monthly' => $latestMonthly,
            'latest_snapshot' => $latestSnapshot,
            'month_pass_rate' => $thisMonth->isNotEmpty()
                ? round(($thisMonth->where('all_passed', true)->count() / $thisMonth->count()) * 100, 2)
                : 0,
            'month_total' => $thisMonth->count(),
            'month_passed' => $thisMonth->where('all_passed', true)->count(),
            'month_failed' => $thisMonth->where('all_passed', false)->count(),
            'open_exceptions' => Exception::open()->count(),
            'overdue_exceptions' => Exception::overdue()->count(),
        ];
    }

    /**
     * Calculate system totals for verification.
     */
    public function getSystemTotals(): array
    {
        $masterBank = MasterAccount::getMasterBank();
        $masterFund = MasterAccount::getMasterFund();

        return [
            'master_bank' => $masterBank->balance,
            'master_fund' => $masterFund->balance,
            'external_banks_total' => ExternalBankAccount::active()->sum('current_balance'),
            'user_banks_total' => Member::whereHas('user', fn($q) => $q->where('status', 'active'))->sum('bank_account_balance'),
            'user_funds_total' => Member::whereHas('user', fn($q) => $q->where('status', 'active'))->sum('fund_account_balance'),
            'outstanding_loans_total' => Loan::active()->sum('outstanding_balance'),
            'active_users' => User::active()->count(),
            'active_loans' => Loan::active()->count(),
        ];
    }

    /**
     * Detect duplicate external bank import.
     */
    public function detectDuplicate(string $externalRefId, int $bankAccountId): bool
    {
        return ExternalBankImport::where('external_ref_id', $externalRefId)
            ->where('external_bank_account_id', $bankAccountId)
            ->exists();
    }

    /**
     * Import external bank transaction.
     */
    public function importExternalTransaction(array $data): ExternalBankImport
    {
        $isDuplicate = $this->detectDuplicate(
            $data['external_ref_id'],
            $data['external_bank_account_id']
        );

        DB::beginTransaction();
        try {
            $import = ExternalBankImport::create([
                ...$data,
                'is_duplicate' => $isDuplicate,
                'imported_to_master' => false,
                'import_date' => now(),
                'imported_by' => auth()->id(),
            ]);

            if (!$isDuplicate) {
                $transaction = Transaction::create([
                    'transaction_id' => Transaction::generateTransactionId('EXT'),
                    'transaction_date' => $data['transaction_date'],
                    'type' => 'external_import',
                    'from_account' => $data['bank_name'] ?? 'External Bank',
                    'to_account' => 'Master Bank Account',
                    'amount' => $data['amount'],
                    'reference' => $data['external_ref_id'],
                    'status' => 'complete',
                    'created_by' => auth()->id(),
                ]);

                $transaction->process();

                $import->update([
                    'imported_to_master' => true,
                    'transaction_id' => $transaction->id,
                ]);
            }

            DB::commit();
            return $import;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
