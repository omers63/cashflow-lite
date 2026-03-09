<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BalanceSnapshot extends Model
{
    protected $fillable = [
        'snapshot_date',
        'period',
        'master_bank',
        'master_fund',
        'external_banks_total',
        'member_banks_total',
        'member_funds_total',
        'outstanding_loans_total',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'snapshot_date' => 'date',
        'master_bank' => 'decimal:2',
        'master_fund' => 'decimal:2',
        'external_banks_total' => 'decimal:2',
        'member_banks_total' => 'decimal:2',
        'member_funds_total' => 'decimal:2',
        'outstanding_loans_total' => 'decimal:2',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
