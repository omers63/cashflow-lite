<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reconciliation extends Model
{
    use HasFactory;

    protected $fillable = [
        'reconciliation_date',
        'type',
        'check_results',
        'all_passed',
        'checks_passed',
        'checks_failed',
        'total_variance',
        'status',
        'notes',
        'performed_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'reconciliation_date' => 'date',
        'check_results' => 'array',
        'all_passed' => 'boolean',
        'total_variance' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    public function performer()
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function exceptions()
    {
        return $this->hasMany(Exception::class, 'related_reconciliation_id');
    }
}
