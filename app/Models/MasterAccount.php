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
        $credits = (float) Transaction::where('target_account', $this->account_type)
            ->where('debit_or_credit', 'credit')
            ->where('status', 'complete')
            ->sum('amount');

        $debits = (float) Transaction::where('target_account', $this->account_type)
            ->where('debit_or_credit', 'debit')
            ->where('status', 'complete')
            ->sum('amount');

        $balance = $credits - $debits;

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
