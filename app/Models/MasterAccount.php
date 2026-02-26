<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MasterAccount extends Model
{
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
}
