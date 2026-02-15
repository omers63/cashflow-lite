<?php

namespace App\Services;

use App\Models\MasterAccount;
use App\Models\User;
use App\Models\Loan;
use App\Models\Transaction;
use App\Models\ExternalBankAccount;
use App\Models\ExternalBankImport;
use App\Models\Reconciliation;
use App\Models\Exception;
use Illuminate\Support\Facades\DB;

class ReconciliationService
{
    protected array $checks = [];
    protected float $tolerance = 0.01;

    /**
     * Run daily reconciliation with all 7 checks
     */
    public function runDailyReconciliation(): Reconciliation
    {
        $this->checks = [];

        // Run all 7 checks
        $this->checkMasterBankEqualsExternalBanks();
        $this->checkUserBanksTotal();
        $this->checkMasterFundBalance();
        $this->checkUserFundAccountsTotal();
        $this->checkTotalOutstandingLoans();
        $this->checkFundBalanceEquation();
        $this->checkCashFlowBalance();

        // Calculate summary
        $allPassed = collect($this->checks)->every(fn($check) => $check['status'] === 'PASS');
        $checksPassed = collect($this->checks)->where('status', 'PASS')->count();
        $checksFailed = collect($this->checks)->where('status', 'FAIL')->count();
        $totalVariance = collect($this->checks)->sum('variance');

        // Create reconciliation record
        $reconciliation = Reconciliation::create([
            'reconciliation_date' => now(),
            'type' => 'daily',
            'check_results' => $this->checks,
            'all_passed' => $allPassed,
            'checks_passed' => $checksPassed,
            'checks_failed' => $checksFailed,
            'total_variance' => abs($totalVariance),
            'status' => $allPassed ? 'complete' : 'failed',
            'performed_by' => auth()->id(),
        ]);

        // Create exceptions for failed checks
        if (!$allPassed) {
            $this->createExceptionsForFailedChecks($reconciliation);
        }

        return $reconciliation;
    }

    /**
     * Check #1: Master Bank Account = Sum of External Bank Accounts
     */
    protected function checkMasterBankEqualsExternalBanks(): void
    {
        $masterBank = MasterAccount::getMasterBank();
        $externalTotal = ExternalBankAccount::active()->sum('current_balance');
        
        $variance = $masterBank->balance - $externalTotal;
        $status = abs($variance) < $this->tolerance ? 'PASS' : 'FAIL';

        $this->checks[] = [
            'check_number' => 1,
            'name' => 'Master Bank Account Balance',
            'description' => 'Master Bank = Sum of External Banks',
            'expected' => $externalTotal,
            'actual' => $masterBank->balance,
            'variance' => $variance,
            'status' => $status,
        ];
    }

    /**
     * Check #2: User Bank Accounts Total
     */
    protected function checkUserBanksTotal(): void
    {
        $userBanksTotal = User::active()->sum('bank_account_balance');
        $masterBank = MasterAccount::getMasterBank();
        $masterFund = MasterAccount::getMasterFund();
        
        $expected = $masterBank->balance - $masterFund->balance;
        $variance = $userBanksTotal - $expected;
        $status = abs($variance) < $this->tolerance ? 'PASS' : 'FAIL';

        $this->checks[] = [
            'check_number' => 2,
            'name' => 'User Bank Accounts Total',
            'description' => 'Sum of User Banks ≤ Master Bank - Master Fund',
            'expected' => $expected,
            'actual' => $userBanksTotal,
            'variance' => $variance,
            'status' => $status,
        ];
    }

    /**
     * Check #3: Master Fund Account Balance
     */
    protected function checkMasterFundBalance(): void
    {
        $masterFund = MasterAccount::getMasterFund();
        $userFundsTotal = User::active()->sum('fund_account_balance');
        $outstandingLoansTotal = Loan::active()->sum('outstanding_balance');
        
        $expected = $userFundsTotal - $outstandingLoansTotal;
        $variance = $masterFund->balance - $expected;
        $status = abs($variance) < $this->tolerance ? 'PASS' : 'FAIL';

        $this->checks[] = [
            'check_number' => 3,
            'name' => 'Master Fund Account',
            'description' => 'Master Fund = User Funds - Outstanding Loans',
            'expected' => $expected,
            'actual' => $masterFund->balance,
            'variance' => $variance,
            'status' => $status,
        ];
    }

    /**
     * Check #4: User Fund Accounts Total
     */
    protected function checkUserFundAccountsTotal(): void
    {
        $userFundsTotal = User::active()->sum('fund_account_balance');
        
        // Calculate from transactions
        $contributions = Transaction::complete()
            ->where('type', 'contribution')
            ->sum('amount');
        $repayments = Transaction::complete()
            ->where('type', 'loan_repayment')
            ->sum('amount');
        
        $expected = $contributions + $repayments;
        $variance = $userFundsTotal - $expected;
        $status = abs($variance) < $this->tolerance ? 'PASS' : 'FAIL';

        $this->checks[] = [
            'check_number' => 4,
            'name' => 'User Fund Accounts Total',
            'description' => 'Sum of User Funds = Contributions + Repayments',
            'expected' => $expected,
            'actual' => $userFundsTotal,
            'variance' => $variance,
            'status' => $status,
        ];
    }

    /**
     * Check #5: Total Outstanding Loans
     */
    protected function checkTotalOutstandingLoans(): void
    {
        $userOutstandingTotal = User::active()->sum('outstanding_loans');
        $loanOutstandingTotal = Loan::active()->sum('outstanding_balance');
        
        $variance = $userOutstandingTotal - $loanOutstandingTotal;
        $status = abs($variance) < $this->tolerance ? 'PASS' : 'FAIL';

        $this->checks[] = [
            'check_number' => 5,
            'name' => 'Total Outstanding Loans',
            'description' => 'User Outstanding Loans = Sum of Loan Balances',
            'expected' => $loanOutstandingTotal,
            'actual' => $userOutstandingTotal,
            'variance' => $variance,
            'status' => $status,
        ];
    }

    /**
     * Check #6: Fund Balance Equation
     */
    protected function checkFundBalanceEquation(): void
    {
        $masterFund = MasterAccount::getMasterFund();
        $userFundsTotal = User::active()->sum('fund_account_balance');
        $outstandingLoansTotal = User::active()->sum('outstanding_loans');
        
        $expected = $userFundsTotal - $outstandingLoansTotal;
        $variance = $masterFund->balance - $expected;
        $status = abs($variance) < $this->tolerance ? 'PASS' : 'FAIL';

        $this->checks[] = [
            'check_number' => 6,
            'name' => 'Fund Balance Check',
            'description' => 'Master Fund = User Funds - User Outstanding Loans',
            'expected' => $expected,
            'actual' => $masterFund->balance,
            'variance' => $variance,
            'status' => $status,
        ];
    }

    /**
     * Check #7: Cash Flow Balance
     */
    protected function checkCashFlowBalance(): void
    {
        $masterBank = MasterAccount::getMasterBank();
        $userBanksTotal = User::active()->sum('bank_account_balance');
        $masterFund = MasterAccount::getMasterFund();
        
        $expected = $userBanksTotal + $masterFund->balance;
        $variance = $masterBank->balance - $expected;
        $status = abs($variance) < $this->tolerance ? 'PASS' : 'FAIL';

        $this->checks[] = [
            'check_number' => 7,
            'name' => 'Cash Flow Balance',
            'description' => 'Master Bank = User Banks + Master Fund',
            'expected' => $expected,
            'actual' => $masterBank->balance,
            'variance' => $variance,
            'status' => $status,
        ];
    }

    /**
     * Create exceptions for failed checks
     */
    protected function createExceptionsForFailedChecks(Reconciliation $reconciliation): void
    {
        foreach ($this->checks as $check) {
            if ($check['status'] === 'FAIL') {
                $variance = abs($check['variance']);
                
                // Determine severity based on variance
                $severity = match(true) {
                    $variance > 1000 => 'critical',
                    $variance > 100 => 'high',
                    $variance > 10 => 'medium',
                    default => 'low',
                };

                Exception::create([
                    'exception_id' => Exception::generateExceptionId(),
                    'type' => 'balance_mismatch',
                    'severity' => $severity,
                    'description' => "Reconciliation Check #{$check['check_number']} failed: {$check['name']}. Expected: {$check['expected']}, Actual: {$check['actual']}, Variance: {$check['variance']}",
                    'variance_amount' => $variance,
                    'related_reconciliation_id' => $reconciliation->id,
                    'status' => 'open',
                    'sla_hours' => Exception::getSlaHours($severity),
                    'sla_deadline' => now()->addHours(Exception::getSlaHours($severity)),
                ]);
            }
        }
    }

    /**
     * Get reconciliation summary for dashboard
     */
    public function getReconciliationSummary(): array
    {
        $latest = Reconciliation::orderBy('reconciliation_date', 'desc')->first();
        $thisMonth = Reconciliation::whereMonth('reconciliation_date', now()->month)
            ->whereYear('reconciliation_date', now()->year)
            ->get();

        return [
            'latest' => $latest,
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
     * Calculate system totals for verification
     */
    public function getSystemTotals(): array
    {
        $masterBank = MasterAccount::getMasterBank();
        $masterFund = MasterAccount::getMasterFund();
        
        return [
            'master_bank' => $masterBank->balance,
            'master_fund' => $masterFund->balance,
            'external_banks_total' => ExternalBankAccount::active()->sum('current_balance'),
            'user_banks_total' => User::active()->sum('bank_account_balance'),
            'user_funds_total' => User::active()->sum('fund_account_balance'),
            'outstanding_loans_total' => Loan::active()->sum('outstanding_balance'),
            'active_users' => User::active()->count(),
            'active_loans' => Loan::active()->count(),
        ];
    }

    /**
     * Detect duplicate external bank import
     */
    public function detectDuplicate(string $externalRefId, int $bankAccountId): bool
    {
        return ExternalBankImport::where('external_ref_id', $externalRefId)
            ->where('external_bank_account_id', $bankAccountId)
            ->exists();
    }

    /**
     * Import external bank transaction
     */
    public function importExternalTransaction(array $data): ExternalBankImport
    {
        // Check for duplicate
        $isDuplicate = $this->detectDuplicate(
            $data['external_ref_id'],
            $data['external_bank_account_id']
        );

        DB::beginTransaction();
        try {
            // Create import record
            $import = ExternalBankImport::create([
                ...$data,
                'is_duplicate' => $isDuplicate,
                'imported_to_master' => false,
                'import_date' => now(),
                'imported_by' => auth()->id(),
            ]);

            // If not duplicate, create transaction and update master bank
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
