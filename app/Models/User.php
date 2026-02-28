<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'user_code',
        'name',
        'email',
        'password',
        'phone',
        'status',
        'bank_account_balance',
        'fund_account_balance',
        'outstanding_loans',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'bank_account_balance' => 'decimal:2',
        'fund_account_balance' => 'decimal:2',
        'outstanding_loans' => 'decimal:2',
    ];

    // Relationships
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    public function activeLoans(): HasMany
    {
        return $this->hasMany(Loan::class)->where('status', 'active');
    }

    public function createdTransactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'created_by');
    }

    public function approvedTransactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'approved_by');
    }

    // Business Logic Methods

    /**
     * Calculate available amount to borrow
     */
    public function getAvailableToBorrowAttribute(): float
    {
        return max(0, $this->fund_account_balance - $this->outstanding_loans);
    }

    /**
     * Check if user has sufficient bank balance
     */
    public function hasSufficientBankBalance(float $amount): bool
    {
        return $this->bank_account_balance >= $amount;
    }

    /**
     * Check if user has sufficient fund balance for loan
     */
    public function canBorrow(float $amount): bool
    {
        return $this->available_to_borrow >= $amount;
    }

    /**
     * Debit user bank account
     */
    public function debitBankAccount(float $amount): void
    {
        if (!$this->hasSufficientBankBalance($amount)) {
            throw new \Exception("Insufficient bank account balance");
        }

        $this->decrement('bank_account_balance', $amount);
    }

    /**
     * Credit user bank account
     */
    public function creditBankAccount(float $amount): void
    {
        $this->increment('bank_account_balance', $amount);
    }

    /**
     * Credit user fund account (contributions/repayments)
     */
    public function creditFundAccount(float $amount): void
    {
        $this->increment('fund_account_balance', $amount);
    }

    /**
     * Debit user fund account (e.g. when reversing a contribution/repayment)
     */
    public function debitFundAccount(float $amount): void
    {
        $this->decrement('fund_account_balance', $amount);
    }

    /**
     * Update outstanding loans total
     */
    public function updateOutstandingLoans(): void
    {
        $total = $this->activeLoans()->sum('outstanding_balance');
        $this->update(['outstanding_loans' => $total]);
    }

    /**
     * Recalculate bank_account_balance from this user's transactions (credits minus debits).
     * Use when the stored balance is out of sync (e.g. after data fixes or imports).
     */
    public function recalculateBankAccountBalanceFromTransactions(): float
    {
        $credits = $this->transactions()
            ->whereIn('type', ['master_to_user_bank', 'loan_disbursement', 'external_import'])
            ->sum('amount');
        $debits = $this->transactions()
            ->where('type', 'contribution')
            ->sum('amount');
        $balance = (float) $credits - (float) $debits;
        $this->update(['bank_account_balance' => $balance]);

        return $balance;
    }

    /**
     * Get transaction history for a date range
     */
    public function getTransactionHistory($startDate, $endDate)
    {
        return $this->transactions()
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->orderBy('transaction_date', 'desc')
            ->get();
    }

    /**
     * Get total contributions
     */
    public function getTotalContributionsAttribute(): float
    {
        return $this->transactions()
            ->where('type', 'contribution')
            ->where('status', 'complete')
            ->sum('amount');
    }

    /**
     * Get total loan repayments
     */
    public function getTotalLoanRepaymentsAttribute(): float
    {
        return $this->transactions()
            ->where('type', 'loan_repayment')
            ->where('status', 'complete')
            ->sum('amount');
    }

    /**
     * Calculate lifetime fund account value
     */
    public function calculateLifetimeFundValue(): float
    {
        return $this->total_contributions + $this->total_loan_repayments;
    }

    /**
     * Check if account is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Suspend user account
     */
    public function suspend(string $reason): void
    {
        $this->update([
            'status' => 'suspended',
        ]);

        activity()
            ->performedOn($this)
            ->withProperties(['reason' => $reason])
            ->log('User account suspended');
    }

    /**
     * Activate user account
     */
    public function activate(): void
    {
        $this->update(['status' => 'active']);

        activity()
            ->performedOn($this)
            ->log('User account activated');
    }

    /**
     * Generate unique user code
     */
    public static function generateUserCode(): string
    {
        $lastUser = static::withTrashed()->orderBy('id', 'desc')->first();
        $nextNumber = $lastUser ? ((int) substr($lastUser->user_code, 4)) + 1 : 1;
        
        return 'USER' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeWithBalances($query)
    {
        return $query->select('*')
            ->selectRaw('(fund_account_balance - outstanding_loans) as available_to_borrow');
    }
}
