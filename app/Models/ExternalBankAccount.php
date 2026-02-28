<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExternalBankAccount extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'bank_name',
        'account_number',
        'account_type',
        'current_balance',
        'currency',
        'status',
        'notes',
    ];

    protected $casts = [
        'current_balance' => 'decimal:2',
    ];

    public function imports(): HasMany
    {
        return $this->hasMany(ExternalBankImport::class);
    }

    /**
     * Recalculate current_balance from this account's imports (sum of amounts for imports posted to master).
     * Use when the stored balance is out of sync (e.g. after deleting imports or data fixes).
     */
    public function recalculateCurrentBalanceFromImports(): float
    {
        $balance = (float) $this->imports()->where('imported_to_master', true)->sum('amount');
        $this->update(['current_balance' => $balance]);
        return $balance;
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
