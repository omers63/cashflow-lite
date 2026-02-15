<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
}
