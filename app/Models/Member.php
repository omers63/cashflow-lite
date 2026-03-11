<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Facades\DB;

class Member extends Model
{
    /** Valid allowed_allocation steps: multiples of 500 from 500 to 3000. */
    public const ALLOCATION_OPTIONS = [500, 1000, 1500, 2000, 2500, 3000];

    /** Minimum fund balance required to be eligible for a loan. */
    public const LOAN_MIN_FUND_BALANCE = 6000;

    /** Minimum membership duration (in years) required for loan eligibility. */
    public const LOAN_MIN_MEMBERSHIP_YEARS = 1;


    protected $fillable = [
        'user_id',
        'parent_id',
        'membership_date',
        'bank_account_balance',
        'fund_account_balance',
        'outstanding_loans',
        'allowed_allocation',
    ];

    protected $casts = [
        'membership_date' => 'date',
        'bank_account_balance' => 'decimal:2',
        'fund_account_balance' => 'decimal:2',
        'outstanding_loans' => 'decimal:2',
        'allowed_allocation' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'parent_id');
    }

    public function dependants(): HasMany
    {
        return $this->hasMany(Member::class, 'parent_id');
    }

    /** Transactions for this member (via the member's user). */
    public function transactions(): HasManyThrough
    {
        return $this->hasManyThrough(Transaction::class, User::class, 'id', 'user_id', 'user_id', 'id');
    }

    /** A parent member has one or more dependants. */
    public function isParentMember(): bool
    {
        return $this->dependants()->exists();
    }

    /** A dependant member has a parent and cannot be a parent. */
    public function isDependantMember(): bool
    {
        return $this->parent_id !== null;
    }

    /**
     * Only members that are not dependants can be selected as a parent.
     */
    public static function eligibleParentsQuery()
    {
        return static::query()->whereNull('parent_id');
    }

    // ─── Loans ──────────────────────────────────────────────────────────────────

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    /** Whether the member has at least one active (non-paid-off) loan. */
    public function hasActiveLoan(): bool
    {
        return $this->loans()->where('status', 'active')->exists();
    }

    /** Membership tenure in years (from membership_date to today). */
    public function membershipYears(): float
    {
        if (! $this->membership_date) {
            return 0;
        }
        return $this->membership_date->diffInDays(now()) / 365.25;
    }

    /** Maximum loan amount: up to 2× fund account balance, capped at $300,000. */
    public function maxLoanAmount(): float
    {
        return min((float) $this->fund_account_balance * 2, 300000.0);
    }

    /**
     * Check whether this member is eligible for a new loan and return
     * a list of failed rules (empty = eligible).
     * @return string[]
     */
    public function loanEligibilityErrors(): array
    {
        $errors = [];

        if ($this->membershipYears() < self::LOAN_MIN_MEMBERSHIP_YEARS) {
            $errors[] = 'Membership must be at least ' . self::LOAN_MIN_MEMBERSHIP_YEARS
                . ' year(s). Current: ' . number_format($this->membershipYears(), 1) . ' years.';
        }

        if ((float) $this->fund_account_balance < self::LOAN_MIN_FUND_BALANCE) {
            $errors[] = 'Fund account balance must be at least $'
                . number_format(self::LOAN_MIN_FUND_BALANCE, 2)
                . '. Current: $' . number_format((float) $this->fund_account_balance, 2) . '.';
        }

        return $errors;
    }

    public function isEligibleForLoan(): bool
    {
        return empty($this->loanEligibilityErrors());
    }

    /**
     * Look up the loan tier for a given loan amount.
     * @return array{min_amount: int, max_amount: int, installment_amount: int, maturity_balance: int}|null
     */
    public static function loanTierFor(float $amount): ?array
    {
        $tiersJson = Setting::get('loan_tiers');
        $tiers = $tiersJson ? json_decode($tiersJson, true) : config('settings.loan_tiers', []);

        foreach ($tiers as $tier) {
            $min = (float) ($tier['min_amount'] ?? 0);
            $max = (float) ($tier['max_amount'] ?? 0);
            if ($amount >= $min && $amount <= $max) {
                $percentage = (float) ($tier['maturity_percentage'] ?? 16);
                $tier['maturity_balance'] = $amount * ($percentage / 100);
                return $tier;
            }
        }
        return null;
    }

    /**
     * Check if a requested loan amount exceeds the Master Fund allocation for its respective tier.
     * Returns an error message string if exceeded, or null if valid.
     */
    public function checkTierAllocation(float $amount): ?string
    {
        $tier = self::loanTierFor($amount);
        if (!$tier) {
            return "Amount outside defined loan tier ranges.";
        }

        $min = (float) ($tier['min_amount'] ?? 0);
        $max = (float) ($tier['max_amount'] ?? 0);
        $allocationPercentage = (float) ($tier['allocation_percentage'] ?? 10);

        $activeLoansInTier = \App\Models\Loan::where('status', 'active')
            ->whereBetween('original_amount', [$min, $max])
            ->sum('outstanding_balance');

        $masterFund = \App\Models\MasterAccount::getMasterFund();
        $masterBalance = $masterFund ? (float) $masterFund->balance : 0.0;

        $allowed = $masterBalance * ($allocationPercentage / 100);

        if (($activeLoansInTier + $amount) > $allowed) {
            $remaining = max(0, $allowed - $activeLoansInTier);
            return "Requested amount ($" . number_format($amount, 2) . ") exceeds the {$tier['name']} allocation limit. Remaining allocation: $" . number_format($remaining, 2) . ".";
        }

        return null;
    }


    // ─── Financials (moved from User) ─────────────────────────────────────────

    /** Calculate available amount to borrow (fund balance minus outstanding loans). */
    public function getAvailableToBorrowAttribute(): float
    {
        return max(0, (float) $this->fund_account_balance - (float) $this->outstanding_loans);
    }

    public function hasSufficientBankBalance(float $amount): bool
    {
        return (float) $this->bank_account_balance >= $amount;
    }

    public function canBorrow(float $amount): bool
    {
        return $this->available_to_borrow >= $amount;
    }

    public function debitBankAccount(float $amount): void
    {
        if (! $this->hasSufficientBankBalance($amount)) {
            throw new \Exception('Insufficient bank account balance');
        }
        $this->decrement('bank_account_balance', $amount);
    }

    public function creditBankAccount(float $amount): void
    {
        $this->increment('bank_account_balance', $amount);
    }

    public function creditFundAccount(float $amount): void
    {
        $this->increment('fund_account_balance', $amount);
    }

    public function debitFundAccount(float $amount): void
    {
        $this->decrement('fund_account_balance', $amount);
    }

    /**
     * Contribute the member's allowed_allocation amount from their bank account to their fund account.
     * Also increments the master fund balance. Creates a proper contribution transaction.
     *
     * @param  \DateTimeInterface|string|null  $transactionDate  Optional date for the contribution (default: now).
     */
    public function contribute(?float $amount = null, ?string $notes = null, \DateTimeInterface|string|null $transactionDate = null): Transaction
    {
        $amount = $amount ?? (int) ($this->allowed_allocation ?? 500);

        if (! $this->hasSufficientBankBalance($amount)) {
            throw new \Exception(
                'Insufficient bank account balance to contribute. ' .
                'Required: $' . number_format($amount, 2) . ', ' .
                'Available: $' . number_format((float) $this->bank_account_balance, 2) . '.'
            );
        }

        $date = $transactionDate ? \Carbon\Carbon::parse($transactionDate) : now();

        return DB::transaction(function () use ($amount, $notes, $date): Transaction {
            $user = $this->user;

            // 1. Debit User Bank
            $debit = Transaction::create([
                'transaction_id' => Transaction::generateTransactionId('CTB-D'),
                'transaction_date' => $date,
                'type' => 'debit',
                'target_account' => 'user_bank',
                'amount' => $amount,
                'user_id' => $user->id,
                'status' => 'pending',
                'notes' => "Contribution Debit: " . ($notes ?? 'Member contribution'),
                'created_by' => auth()->id(),
            ]);
            $debit->process();

            // 2. Credit Master Fund (Auto-credits User Fund)
            $credit = Transaction::create([
                'transaction_id' => Transaction::generateTransactionId('CTB-C'),
                'transaction_date' => $date,
                'type' => 'contribution',
                'target_account' => 'master_fund',
                'amount' => $amount,
                'user_id' => $user->id,
                'related_transaction_id' => $debit->id,
                'status' => 'pending',
                'notes' => "Contribution Credit: " . ($notes ?? 'Member contribution'),
                'created_by' => auth()->id(),
            ]);
            $credit->process();

            return $credit->fresh();
        });
    }

    /**
     * Make a loan repayment for the given loan using its installment amount.
     * Debits member's bank, credits member's fund + master fund, and records the loan payment.
     *
     * @param  \DateTimeInterface|string|null  $transactionDate  Optional date for the repayment (default: now).
     */
    public function makeRepayment(Loan $loan, \DateTimeInterface|string|null $transactionDate = null): Transaction
    {
        if ($loan->status !== 'active') {
            throw new \Exception('Only active loans can receive repayments.');
        }

        $amount = (float) ($loan->installment_amount ?? $loan->monthly_payment);
        $remaining = (float) $loan->outstanding_balance;
        $amount = min($amount, $remaining);

        if (! $this->hasSufficientBankBalance($amount)) {
            throw new \Exception(
                'Insufficient bank account balance for repayment. ' .
                'Required: $' . number_format($amount, 2) . ', ' .
                'Available: $' . number_format((float) $this->bank_account_balance, 2) . '.'
            );
        }

        $date = $transactionDate ? \Carbon\Carbon::parse($transactionDate) : now();

        return DB::transaction(function () use ($loan, $amount, $date): Transaction {
            $user = $this->user;

            // 1. Debit User Bank
            $debit = Transaction::create([
                'transaction_id' => Transaction::generateTransactionId('LNP-D'),
                'transaction_date' => $date,
                'type' => 'debit',
                'target_account' => 'user_bank',
                'amount' => $amount,
                'user_id' => $user->id,
                'reference' => $loan->loan_id,
                'status' => 'pending',
                'notes' => "Loan Repayment Debit: {$loan->loan_id}",
                'created_by' => auth()->id(),
            ]);
            $debit->process();

            // 2. Credit Master Fund (Auto-credits User Fund + updates Loan)
            $credit = Transaction::create([
                'transaction_id' => Transaction::generateTransactionId('LNP-C'),
                'transaction_date' => $date,
                'type' => 'loan_repayment',
                'target_account' => 'master_fund',
                'amount' => $amount,
                'user_id' => $user->id,
                'related_transaction_id' => $debit->id,
                'reference' => $loan->loan_id,
                'status' => 'pending',
                'notes' => "Loan Repayment Credit: {$loan->loan_id}",
                'created_by' => auth()->id(),
            ]);
            $credit->process();

            return $credit->fresh();
        });
    }

    /**
     * Import funds from a CSV, XLS, or XLSX file (two columns: Transaction Date, Amount, header on row 1).
     * For each data row, two transactions are posted at the row's date:
     *   1. external_import (assigned to member) — credits master bank + credits member's bank
     *   2. contribution — debits member's bank, credits member's fund + master fund
     *
     * @param  string  $absolutePath  Absolute filesystem path to the uploaded file.
     * @param  string  $dateFormat    PHP date format string (e.g. 'Y-m-d', 'd/m/Y') or 'auto' to let Carbon guess.
     *                                Ignored for Excel cells that already carry a native date type.
     * @return array{imported: int, skipped: int, errors: string[]}
     */
    public function importFunds(string $absolutePath, string $dateFormat = 'Y-m-d'): array
    {
        $results = ['imported' => 0, 'skipped' => 0, 'errors' => []];

        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($absolutePath);
        $sheet = $spreadsheet->getActiveSheet();
        $highestRow = $sheet->getHighestDataRow();

        if ($highestRow <= 1) {
            return $results; // header only or empty
        }

        $user = $this->user;

        DB::transaction(function () use ($sheet, $highestRow, $user, $dateFormat, &$results): void {
            for ($rowNum = 2; $rowNum <= $highestRow; $rowNum++) {
                $dateCell   = $sheet->getCell("A{$rowNum}");
                $amountCell = $sheet->getCell("B{$rowNum}");

                // Skip completely empty rows
                $rawDate   = $dateCell->getValue();
                $rawAmount = $amountCell->getValue();
                if ($rawDate === null && $rawAmount === null) {
                    continue;
                }

                // ── Resolve date ──────────────────────────────────────────────
                $txDate = null;
                if (\PhpOffice\PhpSpreadsheet\Shared\Date::isDateTime($dateCell) && is_numeric($rawDate)) {
                    // Native Excel date cell — convert serial to Carbon directly
                    $txDate = \Carbon\Carbon::instance(
                        \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($rawDate)
                    );
                } else {
                    $dateStr = trim((string) $dateCell->getFormattedValue());
                    if ($dateStr === '') {
                        $results['skipped']++;
                        $results['errors'][] = "Row {$rowNum}: empty date";
                        continue;
                    }
                    try {
                        $txDate = self::parseDateFlexible($dateStr, $dateFormat);
                    } catch (\Exception $e) {
                        $results['skipped']++;
                        $results['errors'][] = "Row {$rowNum}: cannot parse date '{$dateStr}' with format '{$dateFormat}'";
                        continue;
                    }
                }

                // ── Resolve amount ────────────────────────────────────────────
                $cleanAmount = str_replace(['$', ',', ' '], '', (string) $amountCell->getFormattedValue());
                $amount = (float) $cleanAmount;
                if ($amount <= 0) {
                    $results['skipped']++;
                    $results['errors'][] = "Row {$rowNum}: invalid amount '{$rawAmount}'";
                    continue;
                }

                // Step 1 — credit master bank account (external import)
                $txExternal = Transaction::create([
                    'transaction_id' => Transaction::generateTransactionId('EXT-E'),
                    'transaction_date' => $txDate,
                    'type' => 'import_deposit',
                    // Target specific bank for tracking if we knew it? 
                    // importFunds doesn't specify a bank account ID, so we use dummy or generic.
                    'target_account' => "user_bank", 
                    'amount' => $amount,
                    'user_id' => $user->id,
                    'status' => 'pending',
                    'notes' => 'Bulk Import receipt to bank',
                    'created_by' => auth()->id(),
                ]);
                $txExternal->process();

                $txMaster = Transaction::create([
                    'transaction_id' => Transaction::generateTransactionId('EXT-M'),
                    'transaction_date' => $txDate,
                    'type' => 'external_import',
                    'target_account' => 'master_bank',
                    'amount' => $amount,
                    'related_transaction_id' => $txExternal->id,
                    'status' => 'pending',
                    'notes' => 'Bulk Import Master Bank Credit',
                    'created_by' => auth()->id(),
                ]);
                $txMaster->process();

                // Step 2 — contribution (paired)
                $contribDebit = Transaction::create([
                    'transaction_id' => Transaction::generateTransactionId('CTB-D'),
                    'transaction_date' => $txDate,
                    'type' => 'debit',
                    'target_account' => 'user_bank',
                    'amount' => $amount,
                    'user_id' => $user->id,
                    'status' => 'pending',
                    'notes' => 'Fund import contribution - Bank Debit',
                    'created_by' => auth()->id(),
                ]);
                $contribDebit->process();

                $contribCredit = Transaction::create([
                    'transaction_id' => Transaction::generateTransactionId('CTB-C'),
                    'transaction_date' => $txDate,
                    'type' => 'contribution',
                    'target_account' => 'master_fund',
                    'amount' => $amount,
                    'user_id' => $user->id,
                    'related_transaction_id' => $contribDebit->id,
                    'status' => 'pending',
                    'notes' => 'Fund import contribution - Fund Credit',
                    'created_by' => auth()->id(),
                ]);
                $contribCredit->process();

                $results['imported']++;
            }
        });

        return $results;
    }

    /**
     * Parse a date string flexibly: try the given format first, then the same format with
     * separator variants (swapping / . - and spaces), then fall back to Carbon::parse().
     * This handles cases where the user selects "d-m-Y" but the file actually uses "d/m/Y".
     *
     * @throws \Exception if the date cannot be parsed by any strategy
     */
    private static function parseDateFlexible(string $dateStr, string $format): \Carbon\Carbon
    {
        if ($format === 'auto') {
            return \Carbon\Carbon::parse($dateStr);
        }

        // Try exact format first
        $parsed = \DateTime::createFromFormat($format, $dateStr);
        if ($parsed !== false) {
            return \Carbon\Carbon::instance($parsed);
        }

        // Build separator variants: replace any separator in the format with each alternative
        $separators = ['/', '-', '.', ' '];
        foreach ($separators as $sep) {
            // Replace the first non-alpha, non-Y/m/d character in the format with $sep
            $altFormat = preg_replace('/[\/\-\. ]/', $sep, $format);
            $altDate   = preg_replace('/[\/\-\. ]/', $sep, $dateStr);
            if ($altFormat === $format && $altDate === $dateStr) {
                continue; // no change, already tried above
            }
            $parsed = \DateTime::createFromFormat($altFormat, $altDate);
            if ($parsed !== false) {
                return \Carbon\Carbon::instance($parsed);
            }
        }

        // Last resort: let Carbon guess
        try {
            return \Carbon\Carbon::parse($dateStr);
        } catch (\Exception $e) {
            throw new \Exception("Cannot parse date '{$dateStr}'");
        }
    }

    public function updateOutstandingLoans(): void
    {
        $total = $this->user->activeLoans()->sum('outstanding_balance');
        $this->update(['outstanding_loans' => $total]);
    }

    /**
     * Compute bank account balance from transactions without persisting.
     * Use for reconciliation checks (MEM1). Credits = external_import (when assigned),
     * master_to_user_bank, allocations, import_deposit. Loan disbursement is off-system, so not included.
     */
    public function computeBankAccountBalanceFromTransactions(): float
    {
        $transactions = $this->user->transactions()
            ->where('target_account', 'user_bank')
            ->where('status', 'complete')
            ->get();
            
        return $transactions->sum(function($tx) {
            $isCredit = in_array($tx->type, ['credit', 'external_import', 'allocation_from_parent', 'import_deposit', 'loan_disbursement']);
            return $isCredit ? (float)$tx->amount : -(float)$tx->amount;
        });
    }

    /**
     * Compute fund account balance from transactions without persisting.
     * Use for reconciliation checks (MEM2).
     */
    public function computeFundAccountBalanceFromTransactions(): float
    {
        // Fund share is updated whenever master_fund is targeting a user, or direct user_fund.
        $transactions = $this->user->transactions()
            ->where(fn($q) => $q->where('target_account', 'master_fund')->orWhere('target_account', 'user_fund'))
            ->where('status', 'complete')
            ->get();

        return $transactions->sum(function($tx) {
            $isCredit = in_array($tx->type, ['contribution', 'loan_repayment', 'credit']);
            return $isCredit ? (float)$tx->amount : -(float)$tx->amount;
        });
    }

    /**
     * Recalculate bank_account_balance from this member's user transactions.
     */
    public function recalculateBankAccountBalanceFromTransactions(): float
    {
        $balance = $this->computeBankAccountBalanceFromTransactions();
        $this->update(['bank_account_balance' => $balance]);
        return $balance;
    }

    /**
     * Recalculate fund_account_balance from this member's user transactions.
     * Credits: contribution, loan_repayment.
     */
    public function recalculateFundAccountBalanceFromTransactions(): float
    {
        $balance = $this->computeFundAccountBalanceFromTransactions();
        $this->update(['fund_account_balance' => $balance]);
        return $balance;
    }

    /**
     * Allocate funds from this member's bank account to a dependant's bank account.
     * Creates two linked transactions (allocation_to_dependant / allocation_from_parent) and updates balances.
     *
     * @param  \DateTimeInterface|string|null  $transactionDate  Optional date for the allocation (default: now).
     */
    public function allocateToDependant(Member $dependent, float $amount, ?string $notes = null, \DateTimeInterface|string|null $transactionDate = null): void
    {
        if ($dependent->parent_id !== $this->id) {
            throw new \InvalidArgumentException('The target member is not a dependant of this member.');
        }
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Amount must be positive.');
        }
        if (! $this->hasSufficientBankBalance($amount)) {
            throw new \Exception('Insufficient bank account balance to allocate.');
        }

        $date = $transactionDate ? \Carbon\Carbon::parse($transactionDate) : now();
        $parentUser = $this->user;
        $dependentUser = $dependent->user;

        DB::transaction(function () use ($dependent, $amount, $notes, $date, $parentUser, $dependentUser): void {
            // Pair: Debit Parent Bank, Credit Dependent Bank
            
            $outTx = Transaction::create([
                'transaction_id' => Transaction::generateTransactionId('AL-OUT'),
                'transaction_date' => $date,
                'type' => 'debit',
                'target_account' => 'user_bank',
                'amount' => $amount,
                'user_id' => $this->user_id,
                'status' => 'pending',
                'notes' => "Allocation OUT to dependant: " . ($dependentUser->name ?? "Member #{$dependent->id}"),
                'created_by' => auth()->id(),
            ]);
            $outTx->process();

            $inTx = Transaction::create([
                'transaction_id' => Transaction::generateTransactionId('AL-IN'),
                'transaction_date' => $date,
                'type' => 'allocation_from_parent',
                'target_account' => 'user_bank',
                'amount' => $amount,
                'user_id' => $dependent->user_id,
                'related_transaction_id' => $outTx->id,
                'status' => 'pending',
                'notes' => "Allocation IN from parent: " . ($parentUser->name ?? "Member #{$this->id}"),
                'created_by' => auth()->id(),
            ]);
            $inTx->process();
        });
    }

    protected static function booted(): void
    {
        static::saving(function (Member $member): void {
            // Parent must not be a dependant (only non-dependant members can be parents).
            if ($member->parent_id !== null) {
                $parent = Member::find($member->parent_id);
                if ($parent && $parent->parent_id !== null) {
                    throw new \InvalidArgumentException('A dependant member cannot be a parent. Please select a member who is not a dependant.');
                }
            }
            // A member who already has dependants cannot become a dependant.
            if ($member->exists && $member->parent_id !== null && $member->dependants()->exists()) {
                throw new \InvalidArgumentException('A member with dependants cannot be set as a dependant.');
            }
        });
    }
}
