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
            ->whereIn('type', ['master_to_user_bank', 'loan_disbursement', 'external_import', 'allocation_from_parent'])
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

        // Dependant's bank balance cap: cannot exceed their allowed_allocation.
        $depCap = (int) ($dependent->allowed_allocation ?? 500);
        $depCurrentBalance = (float) $dependent->bank_account_balance;
        $depRoom = max(0, $depCap - $depCurrentBalance);
        if ($amount > $depRoom) {
            throw new \Exception(
                "This allocation would exceed the dependant's bank balance cap ($" .
                number_format($depCap, 2) . '). ' .
                'Current balance: $' . number_format($depCurrentBalance, 2) . ', ' .
                'available room: $' . number_format($depRoom, 2) . '.'
            );
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
