<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Exception extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'exception_id',
        'type',
        'severity',
        'description',
        'affected_accounts',
        'variance_amount',
        'related_transaction_id',
        'related_reconciliation_id',
        'status',
        'resolution_steps',
        'resolution_notes',
        'assigned_to',
        'resolved_by',
        'resolved_at',
        'sla_hours',
        'sla_deadline',
        'sla_breached',
    ];

    protected $casts = [
        'affected_accounts' => 'array',
        'variance_amount' => 'decimal:2',
        'resolved_at' => 'datetime',
        'sla_deadline' => 'datetime',
        'sla_breached' => 'boolean',
    ];

    public function relatedTransaction()
    {
        return $this->belongsTo(Transaction::class, 'related_transaction_id');
    }

    public function relatedReconciliation()
    {
        return $this->belongsTo(Reconciliation::class, 'related_reconciliation_id');
    }

    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function resolver()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public static function generateExceptionId(): string
    {
        $date = now()->format('Ymd');
        $count = static::whereDate('created_at', now())->count() + 1;
        
        return sprintf('EXC-%s-%04d', $date, $count);
    }

    public static function getSlaHours(string $severity): int
    {
        return match($severity) {
            'critical' => 1,
            'high' => 2,
            'medium' => 4,
            'low' => 24,
            default => 24,
        };
    }

    public function scopeOpen($query)
    {
        return $query->whereIn('status', ['open', 'under_investigation']);
    }

    public function scopeOverdue($query)
    {
        return $query->where('sla_deadline', '<', now())
            ->where('status', '!=', 'resolved');
    }
}
