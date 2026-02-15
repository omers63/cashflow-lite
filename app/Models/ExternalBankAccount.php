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

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
