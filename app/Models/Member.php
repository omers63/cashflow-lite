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

    protected $fillable = [
        'user_id',
        'parent_id',
        'bank_account_balance',
        'fund_account_balance',
        'outstanding_loans',
        'allowed_allocation',
    ];

    protected $casts = [
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
     */
    public function contribute(?string $notes = null): Transaction
    {
        $amount = (int) ($this->allowed_allocation ?? 500);

        if (! $this->hasSufficientBankBalance($amount)) {
            throw new \Exception(
                'Insufficient bank account balance to contribute. ' .
                'Required: $' . number_format($amount, 2) . ', ' .
                'Available: $' . number_format((float) $this->bank_account_balance, 2) . '.'
            );
        }

        return DB::transaction(function () use ($amount, $notes): Transaction {
            $user = $this->user;

            $transaction = Transaction::create([
                'transaction_id' => Transaction::generateTransactionId('CTB'),
                'transaction_date' => now(),
                'type' => 'contribution',
                'from_account' => "Member Bank Account - {$user->user_code}",
                'to_account' => "Member Fund Account - {$user->user_code}",
                'amount' => $amount,
                'user_id' => $user->id,
                'status' => 'pending',
                'notes' => $notes ?? 'Member contribution',
                'created_by' => auth()->id(),
            ]);

            $transaction->process();

            return $transaction->fresh();
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
                $extImportTx = Transaction::create([
                    'transaction_id' => Transaction::generateTransactionId('EXT'),
                    'transaction_date' => $txDate,
                    'type' => 'external_import',
                    'from_account' => 'External Source',
                    'to_account' => 'Master Bank Account',
                    'amount' => $amount,
                    'user_id' => $user->id,
                    'status' => 'pending',
                    'notes' => 'Fund import – external receipt',
                    'created_by' => auth()->id(),
                ]);
                $extImportTx->process();

                // Step 2 — debit member's bank, credit member's fund + master fund
                $contribTx = Transaction::create([
                    'transaction_id' => Transaction::generateTransactionId('CTB'),
                    'transaction_date' => $txDate,
                    'type' => 'contribution',
                    'from_account' => "Member Bank Account - {$user->user_code}",
                    'to_account' => "Member Fund Account - {$user->user_code}",
                    'amount' => $amount,
                    'user_id' => $user->id,
                    'status' => 'pending',
                    'notes' => 'Fund import – contribution',
                    'created_by' => auth()->id(),
                ]);
                $contribTx->process();

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
     * Recalculate bank_account_balance from this member's user transactions.
     */
    public function recalculateBankAccountBalanceFromTransactions(): float
    {
        $credits = $this->user->transactions()
            ->whereIn('type', ['master_to_user_bank', 'loan_disbursement', 'external_import', 'allocation_from_parent', 'import_deposit'])
            ->sum('amount');
        $debits = $this->user->transactions()
            ->whereIn('type', ['contribution', 'allocation_to_dependant'])
            ->sum('amount');
        $balance = (float) $credits - (float) $debits;
        $this->update(['bank_account_balance' => $balance]);

        return $balance;
    }

    /**
     * Allocate funds from this member's bank account to a dependant's bank account.
     * Creates two linked transactions (allocation_to_dependant / allocation_from_parent) and updates balances.
     */
    public function allocateToDependant(Member $dependent, float $amount, ?string $notes = null): void
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

        DB::transaction(function () use ($dependent, $amount, $notes): void {
            $txIdOut = Transaction::generateTransactionId('ALLOUT');
            $txIdIn = Transaction::generateTransactionId('ALLOIN');

            $outTx = Transaction::create([
                'transaction_id' => $txIdOut,
                'transaction_date' => now(),
                'type' => 'allocation_to_dependant',
                'from_account' => 'member_bank',
                'to_account' => 'member_bank',
                'amount' => $amount,
                'user_id' => $this->user_id,
                'reference' => null,
                'status' => 'complete',
                'notes' => $notes ?? 'Allocation to dependant: ' . ($dependent->user?->name ?? "Member #{$dependent->id}"),
                'created_by' => auth()->id(),
            ]);

            $outTx->update(['allocation_pair_id' => $outTx->id]);

            $inTx = Transaction::create([
                'transaction_id' => $txIdIn,
                'transaction_date' => now(),
                'type' => 'allocation_from_parent',
                'from_account' => 'member_bank',
                'to_account' => 'member_bank',
                'amount' => $amount,
                'user_id' => $dependent->user_id,
                'reference' => null,
                'status' => 'complete',
                'notes' => $notes ?? 'Allocation from parent: ' . ($this->user?->name ?? "Member #{$this->id}"),
                'created_by' => auth()->id(),
                'allocation_pair_id' => $outTx->id,
            ]);

            $this->debitBankAccount($amount);
            $dependent->creditBankAccount($amount);
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
