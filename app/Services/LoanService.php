<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\LoanDisbursement;
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
     * Approve and disburse a loan. If the loan has a disbursement schedule (multiple parts),
     * creates one debit/credit pair per part with that part's date; first repayment is
     * the cycle after the last (fully) disbursed date. Otherwise creates a single
     * disbursement at today.
     */
    public function approveLoan(Loan $loan): ?Transaction
    {
        if ($loan->status !== 'pending') {
            throw new \Exception("Only pending loans can be approved");
        }

        $masterFund = MasterAccount::where('account_type', 'master_fund')->first();
        if (! $masterFund || (float) $masterFund->balance < (float) $loan->original_amount) {
            throw new \Exception("Insufficient master fund balance");
        }

        return DB::transaction(function () use ($loan) {
            $disbursements = $loan->disbursements()->orderBy('disbursement_date')->get();
            $lastCredit = null;

            if ($disbursements->isNotEmpty()) {
                foreach ($disbursements as $disb) {
                    $date = $disb->disbursement_date instanceof Carbon
                        ? $disb->disbursement_date
                        : Carbon::parse($disb->disbursement_date);
                    $amount = (float) $disb->amount;

                    $debit = Transaction::create([
                        'transaction_id' => Transaction::generateTransactionId('LND-D'),
                        'transaction_date' => $date,
                        'type' => 'debit',
                        'debit_or_credit' => 'debit',
                        'target_account' => 'master_fund',
                        'amount' => $amount,
                        'user_id' => $loan->user_id,
                        'reference' => $loan->loan_id,
                        'status' => 'pending',
                        'notes' => "Loan Disbursement Debit from Fund: {$loan->loan_id}",
                        'created_by' => auth()->id(),
                    ]);
                    $debit->process();

                    $credit = Transaction::create([
                        'transaction_id' => Transaction::generateTransactionId('LND-C'),
                        'transaction_date' => $date,
                        'type' => 'loan_disbursement',
                        'debit_or_credit' => 'credit',
                        'target_account' => 'user_bank',
                        'amount' => $amount,
                        'user_id' => $loan->user_id,
                        'related_transaction_id' => $debit->id,
                        'reference' => $loan->loan_id,
                        'status' => 'pending',
                        'notes' => "Loan Disbursement Credit to User Bank: {$loan->loan_id}",
                        'created_by' => auth()->id(),
                    ]);
                    $credit->process();

                    $disb->update([
                        'fund_debit_transaction_id' => $debit->id,
                        'user_credit_transaction_id' => $credit->id,
                    ]);
                    $lastCredit = $credit;
                }

                $fullyDisbursedDate = Carbon::parse($disbursements->max('disbursement_date'));
                $loan->update(['fully_disbursed_date' => $fullyDisbursedDate]);
                $loan->approve(auth()->id(), $fullyDisbursedDate);
            } else {
                $debit = Transaction::create([
                    'transaction_id' => Transaction::generateTransactionId('LND-D'),
                    'transaction_date' => now(),
                    'type' => 'debit',
                    'debit_or_credit' => 'debit',
                    'target_account' => 'master_fund',
                    'amount' => $loan->original_amount,
                    'user_id' => $loan->user_id,
                    'reference' => $loan->loan_id,
                    'status' => 'pending',
                    'notes' => "Loan Disbursement Debit from Fund: {$loan->loan_id}",
                    'created_by' => auth()->id(),
                ]);
                $debit->process();

                $credit = Transaction::create([
                    'transaction_id' => Transaction::generateTransactionId('LND-C'),
                    'transaction_date' => now(),
                    'type' => 'loan_disbursement',
                    'debit_or_credit' => 'credit',
                    'target_account' => 'user_bank',
                    'amount' => $loan->original_amount,
                    'user_id' => $loan->user_id,
                    'related_transaction_id' => $debit->id,
                    'reference' => $loan->loan_id,
                    'status' => 'pending',
                    'notes' => "Loan Disbursement Credit to User Bank: {$loan->loan_id}",
                    'created_by' => auth()->id(),
                ]);
                $credit->process();
                $lastCredit = $credit;

                $loan->approve(auth()->id(), null);
            }

            return $lastCredit ? $lastCredit->fresh() : null;
        });
    }

    /**
     * Process a loan payment (Paired: Debit User Bank, Credit Master Fund)
     *
     * @param  \Carbon\Carbon|string|null  $transactionDate  Defaults to today
     */
    public function processPayment(Loan $loan, float $amount, ?string $notes = null, Carbon|string|null $transactionDate = null): Transaction
    {
        if ($loan->status !== 'active') {
            throw new \Exception("Can only make payments on active loans");
        }

        if (! $loan->user->hasSufficientBankBalance($amount)) {
            throw new \Exception("Insufficient bank account balance");
        }

        $date = $transactionDate ? Carbon::parse($transactionDate)->startOfDay() : now()->startOfDay();
        $dateOnly = $date->toDateString();

        return DB::transaction(function () use ($loan, $amount, $notes, $dateOnly) {
            $debit = Transaction::create([
                'transaction_id' => Transaction::generateTransactionId('LNP-D'),
                'transaction_date' => $dateOnly,
                'type' => 'debit',
                'debit_or_credit' => 'debit',
                'target_account' => 'user_bank',
                'amount' => $amount,
                'user_id' => $loan->user_id,
                'reference' => $loan->loan_id,
                'status' => 'pending',
                'notes' => "Loan Payment Debit from Bank: {$loan->loan_id}",
                'created_by' => auth()->id(),
            ]);
            $debit->process();

            $credit = Transaction::create([
                'transaction_id' => Transaction::generateTransactionId('LNP-C'),
                'transaction_date' => $dateOnly,
                'type' => 'loan_repayment',
                'debit_or_credit' => 'credit',
                'target_account' => 'master_fund',
                'amount' => $amount,
                'user_id' => $loan->user_id,
                'related_transaction_id' => $debit->id,
                'reference' => $loan->loan_id,
                'status' => 'pending',
                'notes' => $notes ?? "Loan payment Credit to Fund: {$loan->loan_id}",
                'created_by' => auth()->id(),
            ]);
            $credit->process();

            return $credit->fresh();
        });
    }

    /**
     * Post an extra disbursement for an already-active loan (fund debit + member bank credit).
     * Creates a disbursement row and refreshes repayment dates from the schedule.
     */
    public function postAdditionalDisbursement(Loan $loan, float $amount, Carbon|string $transactionDate, ?string $notes = null): Transaction
    {
        if ($loan->status !== 'active') {
            throw new \Exception('Additional disbursements can only be posted for active loans.');
        }
        if ($amount <= 0) {
            throw new \Exception('Amount must be greater than zero.');
        }

        $masterFund = MasterAccount::where('account_type', 'master_fund')->first();
        if (! $masterFund || (float) $masterFund->balance < $amount) {
            throw new \Exception('Insufficient master fund balance');
        }

        $date = Carbon::parse($transactionDate)->startOfDay();
        $dateOnly = $date->toDateString();

        return DB::transaction(function () use ($loan, $amount, $dateOnly, $notes) {
            $debit = Transaction::create([
                'transaction_id' => Transaction::generateTransactionId('LND-D'),
                'transaction_date' => $dateOnly,
                'type' => 'debit',
                'debit_or_credit' => 'debit',
                'target_account' => 'master_fund',
                'amount' => $amount,
                'user_id' => $loan->user_id,
                'reference' => $loan->loan_id,
                'status' => 'pending',
                'notes' => $notes ?? "Additional loan disbursement debit from fund: {$loan->loan_id}",
                'created_by' => auth()->id(),
            ]);
            $debit->process();

            $credit = Transaction::create([
                'transaction_id' => Transaction::generateTransactionId('LND-C'),
                'transaction_date' => $dateOnly,
                'type' => 'loan_disbursement',
                'debit_or_credit' => 'credit',
                'target_account' => 'user_bank',
                'amount' => $amount,
                'user_id' => $loan->user_id,
                'related_transaction_id' => $debit->id,
                'reference' => $loan->loan_id,
                'status' => 'pending',
                'notes' => $notes ?? "Additional loan disbursement credit to bank: {$loan->loan_id}",
                'created_by' => auth()->id(),
            ]);
            $credit->process();

            LoanDisbursement::create([
                'loan_id' => $loan->id,
                'disbursement_date' => $dateOnly,
                'amount' => $amount,
                'fund_debit_transaction_id' => $debit->id,
                'user_credit_transaction_id' => $credit->id,
            ]);

            $loan->refresh();
            $loan->update(['fully_disbursed_date' => $loan->disbursements()->max('disbursement_date')]);
            $loan->updateRepaymentAndMaturityDatesFromDisbursements();

            return $credit->fresh();
        });
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
