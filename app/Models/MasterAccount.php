<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MasterAccount extends Model
{
    /**
     * Recalculate balance from transactions and update the stored balance.
     * Use when the stored balance is out of sync (e.g. after data fixes or when there are no transactions but balance is non-zero).
     */
    public function recalculateBalanceFromTransactions(): float
    {
        $query = Transaction::query();

        if ($this->account_type === 'master_bank') {
            $credits = (float) (clone $query)->where('type', 'external_import')->sum('amount');
            $debits = (float) (clone $query)
                ->whereIn('type', ['master_to_user_bank', 'loan_disbursement'])
                ->sum('amount');
            $balance = $credits - $debits;
        } elseif ($this->account_type === 'master_fund') {
            $credits = (float) (clone $query)->whereIn('type', ['contribution', 'loan_repayment'])->sum('amount');
            $balance = $credits;
        } else {
            return (float) $this->balance;
        }

        $this->update(['balance' => $balance]);
        return $balance;
    }

    use HasFactory;

    protected $fillable = [
        'account_type',
        'balance',
        'opening_balance',
        'balance_date',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'opening_balance' => 'decimal:2',
        'balance_date' => 'date',
    ];

    public static function getMasterBank(): self
    {
        return static::where('account_type', 'master_bank')->first();
    }

    public static function getMasterFund(): self
    {
        return static::where('account_type', 'master_fund')->first();
    }

    public function getFormattedBalanceAttribute(): string
    {
        return '$' . number_format($this->balance, 2);
    }

    /**
     * Dummy relationship used by Filament relation manager.
     * The actual query is customized in the relation manager.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'id', 'id');
    }

    /**
     * Dummy relationship used by the external bank accounts relation manager.
     * The actual query is customized in the relation manager.
     */
    public function externalBankAccounts(): HasMany
    {
        return $this->hasMany(ExternalBankAccount::class, 'id', 'id');
    }
}
