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

class ExternalBankImport extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'external_bank_account_id',
        'import_date',
        'transaction_date',
        'external_ref_id',
        'amount',
        'description',
        'is_duplicate',
        'imported_to_master',
        'transaction_id',
        'notes',
        'imported_by',
    ];

    protected $casts = [
        'import_date' => 'date',
        'transaction_date' => 'datetime',
        'amount' => 'decimal:2',
        'is_duplicate' => 'boolean',
        'imported_to_master' => 'boolean',
    ];

    public function externalBankAccount()
    {
        return $this->belongsTo(ExternalBankAccount::class);
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function importer()
    {
        return $this->belongsTo(User::class, 'imported_by');
    }
}

class LoanPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'loan_id',
        'transaction_id',
        'payment_date',
        'payment_amount',
        'principal_amount',
        'interest_amount',
        'balance_after_payment',
        'payment_type',
        'notes',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'payment_amount' => 'decimal:2',
        'principal_amount' => 'decimal:2',
        'interest_amount' => 'decimal:2',
        'balance_after_payment' => 'decimal:2',
    ];

    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }
}

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
