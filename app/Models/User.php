<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class User extends Authenticatable implements FilamentUser
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'user_code',
        'name',
        'email',
        'password',
        'phone',
        'status',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function canAccessPanel(Panel $panel): bool
    {
        return match ($panel->getId()) {
            'admin' => $this->isAdmin(),
            'member' => $this->member !== null,
            default => false,
        };
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, ['admin', 'super_admin'], true);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    protected $appends = [
        'bank_account_balance',
        'fund_account_balance',
        'outstanding_loans',
    ];

    // Relationships
    public function member(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Member::class);
    }

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

    // Financials: delegated to Member (only members have balances). Accessors for display/form compatibility.

    public function getBankAccountBalanceAttribute(): float
    {
        return $this->member ? (float) $this->member->bank_account_balance : 0.0;
    }

    public function getFundAccountBalanceAttribute(): float
    {
        return $this->member ? (float) $this->member->fund_account_balance : 0.0;
    }

    public function getOutstandingLoansAttribute(): float
    {
        return $this->member ? (float) $this->member->outstanding_loans : 0.0;
    }

    public function getAvailableToBorrowAttribute(): float
    {
        return $this->member ? $this->member->available_to_borrow : 0.0;
    }

    public function hasSufficientBankBalance(float $amount): bool
    {
        if (! $this->member) {
            return false;
        }
        return $this->member->hasSufficientBankBalance($amount);
    }

    public function canBorrow(float $amount): bool
    {
        return $this->member && $this->member->canBorrow($amount);
    }

    public function debitBankAccount(float $amount): void
    {
        if (! $this->member) {
            throw new \RuntimeException('User must be a member to perform bank operations.');
        }
        $this->member->debitBankAccount($amount);
    }

    public function creditBankAccount(float $amount): void
    {
        if (! $this->member) {
            throw new \RuntimeException('User must be a member to perform bank operations.');
        }
        $this->member->creditBankAccount($amount);
    }

    public function creditFundAccount(float $amount): void
    {
        if (! $this->member) {
            throw new \RuntimeException('User must be a member to perform fund operations.');
        }
        $this->member->creditFundAccount($amount);
    }

    public function debitFundAccount(float $amount): void
    {
        if (! $this->member) {
            throw new \RuntimeException('User must be a member to perform fund operations.');
        }
        $this->member->debitFundAccount($amount);
    }

    public function updateOutstandingLoans(): void
    {
        if ($this->member) {
            $this->member->updateOutstandingLoans();
        }
    }

    public function recalculateBankAccountBalanceFromTransactions(): float
    {
        if (! $this->member) {
            throw new \RuntimeException('User must be a member to recalculate bank balance.');
        }
        return $this->member->recalculateBankAccountBalanceFromTransactions();
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

    /**
     * Join members so that bank_account_balance, fund_account_balance, outstanding_loans
     * are available for listing/sorting/summarizing (financials live on Member).
     */
    public function scopeWithMemberBalances($query)
    {
        return $query->leftJoin('members', 'users.id', '=', 'members.user_id')
            ->select('users.*')
            ->selectRaw('COALESCE(members.bank_account_balance, 0) as bank_account_balance')
            ->selectRaw('COALESCE(members.fund_account_balance, 0) as fund_account_balance')
            ->selectRaw('COALESCE(members.outstanding_loans, 0) as outstanding_loans');
    }

    public function scopeWithBalances($query)
    {
        return $query->withMemberBalances()
            ->selectRaw('(COALESCE(members.fund_account_balance, 0) - COALESCE(members.outstanding_loans, 0)) as available_to_borrow');
    }
}
