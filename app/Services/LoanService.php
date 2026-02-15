<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\User;
use App\Models\Transaction;
use App\Models\MasterAccount;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class LoanService
{
    /**
     * Create a new loan application
     */
    public function createLoan(
        User $user,
        float $amount,
        float $interestRate,
        int $termMonths,
        ?string $notes = null
    ): Loan {
        // Validate user can borrow
        if (!$user->canBorrow($amount)) {
            throw new \Exception("User cannot borrow this amount. Available: $" . number_format($user->available_to_borrow, 2));
        }

        // Calculate monthly payment
        $monthlyPayment = Loan::calculateMonthlyPayment($amount, $interestRate, $termMonths);

        DB::beginTransaction();
        try {
            $loan = Loan::create([
                'loan_id' => Loan::generateLoanId(),
                'user_id' => $user->id,
                'origination_date' => now(),
                'original_amount' => $amount,
                'interest_rate' => $interestRate,
                'term_months' => $termMonths,
                'monthly_payment' => $monthlyPayment,
                'outstanding_balance' => $amount,
                'status' => 'pending',
                'notes' => $notes,
            ]);

            DB::commit();
            return $loan;

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Approve and disburse a loan
     */
    public function approveLoan(Loan $loan): Transaction
    {
        if ($loan->status !== 'pending') {
            throw new \Exception("Only pending loans can be approved");
        }

        $masterFund = MasterAccount::where('account_type', 'master_fund')->first();

        if ($masterFund->balance < $loan->original_amount) {
            throw new \Exception("Insufficient master fund balance");
        }

        DB::beginTransaction();
        try {
            // Approve the loan
            $loan->approve(auth()->id());

            // Create disbursement transaction
            $transaction = Transaction::create([
                'transaction_id' => Transaction::generateTransactionId('LND'),
                'transaction_date' => now(),
                'type' => 'loan_disbursement',
                'from_account' => 'Master Fund Account',
                'to_account' => "User Bank Account - {$loan->user->user_code}",
                'amount' => $loan->original_amount,
                'user_id' => $loan->user_id,
                'reference' => $loan->loan_id,
                'status' => 'pending',
                'notes' => "Loan disbursement: {$loan->loan_id}",
                'created_by' => auth()->id(),
            ]);

            $transaction->process();

            DB::commit();
            return $transaction->fresh();

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Process a loan payment
     */
    public function processPayment(Loan $loan, float $amount, ?string $notes = null): Transaction
    {
        if ($loan->status !== 'active') {
            throw new \Exception("Can only make payments on active loans");
        }

        if (!$loan->user->hasSufficientBankBalance($amount)) {
            throw new \Exception("Insufficient bank account balance");
        }

        DB::beginTransaction();
        try {
            $transaction = Transaction::create([
                'transaction_id' => Transaction::generateTransactionId('LNP'),
                'transaction_date' => now(),
                'type' => 'loan_repayment',
                'from_account' => "User Bank Account - {$loan->user->user_code}",
                'to_account' => "User Fund Account - {$loan->user->user_code}",
                'amount' => $amount,
                'user_id' => $loan->user_id,
                'reference' => $loan->loan_id,
                'status' => 'pending',
                'notes' => $notes ?? "Loan payment: {$loan->loan_id}",
                'created_by' => auth()->id(),
            ]);

            $transaction->process();

            DB::commit();
            return $transaction->fresh();

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Get delinquent loans
     */
    public function getDelinquentLoans(): \Illuminate\Database\Eloquent\Collection
    {
        return Loan::delinquent()->with('user')->get();
    }

    /**
     * Get loans due soon
     */
    public function getLoansDueSoon(int $days = 7): \Illuminate\Database\Eloquent\Collection
    {
        return Loan::dueSoon($days)->with('user')->get();
    }

    /**
     * Get loan portfolio summary
     */
    public function getPortfolioSummary(): array
    {
        $activeLoans = Loan::active();

        return [
            'total_active_loans' => $activeLoans->count(),
            'total_outstanding' => $activeLoans->sum('outstanding_balance'),
            'total_original' => $activeLoans->sum('original_amount'),
            'total_paid' => $activeLoans->sum('total_paid'),
            'average_interest_rate' => $activeLoans->avg('interest_rate'),
            'delinquent_count' => Loan::delinquent()->count(),
            'paid_off_count' => Loan::where('status', 'paid_off')->count(),
        ];
    }
}
