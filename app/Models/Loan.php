<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

/**
 * @property float $total_paid
 * @property float $outstanding_balance
 * @property Carbon|null $next_payment_date
 */
class Loan extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'loan_id',
        'user_id',
        'origination_date',
        'original_amount',
        'interest_rate',
        'term_months',
        'monthly_payment',
        'total_paid',
        'outstanding_balance',
        'status',
        'next_payment_date',
        'approved_by',
        'approved_at',
        'notes',
    ];

    protected $casts = [
        'origination_date' => 'date',
        'original_amount' => 'decimal:2',
        'interest_rate' => 'decimal:2',
        'monthly_payment' => 'decimal:2',
        'total_paid' => 'decimal:2',
        'outstanding_balance' => 'decimal:2',
        'next_payment_date' => 'date',
        'approved_at' => 'datetime',
    ];

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(LoanPayment::class);
    }

    // Business Logic

    /**
     * Generate unique loan ID
     */
    public static function generateLoanId(): string
    {
        $date = now()->format('Ymd');
        $count = static::whereDate('created_at', now())->count() + 1;
        
        return sprintf('LOAN-%s-%03d', $date, $count);
    }

    /**
     * Calculate monthly payment using amortization formula
     * P = L[c(1 + c)^n] / [(1 + c)^n - 1]
     */
    public static function calculateMonthlyPayment(
        float $loanAmount,
        float $annualRate,
        int $termMonths
    ): float {
        if ($annualRate == 0) {
            return $loanAmount / $termMonths;
        }

        $monthlyRate = $annualRate / 100 / 12;
        $payment = $loanAmount * 
            ($monthlyRate * pow(1 + $monthlyRate, $termMonths)) / 
            (pow(1 + $monthlyRate, $termMonths) - 1);

        return round($payment, 2);
    }

    /**
     * Calculate interest for current period
     */
    public function calculateInterest(): float
    {
        $monthlyRate = $this->interest_rate / 100 / 12;
        return round($this->outstanding_balance * $monthlyRate, 2);
    }

    /**
     * Process a payment for this loan
     */
    public function processPayment(Transaction $transaction): void
    {
        $paymentAmount = $transaction->amount;
        $interest = $this->calculateInterest();
        $principal = max(0, $paymentAmount - $interest);

        // Create loan payment record
        LoanPayment::create([
            'loan_id' => $this->id,
            'transaction_id' => $transaction->id,
            'payment_date' => $transaction->transaction_date,
            'payment_amount' => $paymentAmount,
            'principal_amount' => $principal,
            'interest_amount' => $interest,
            'balance_after_payment' => max(0, $this->outstanding_balance - $principal),
        ]);

        // Update loan
        $newTotalPaid = (float) ($this->total_paid + $paymentAmount);
        $newBalance = (float) max(0, $this->outstanding_balance - $principal);
        
        $this->total_paid = $newTotalPaid;
        $this->outstanding_balance = $newBalance;

        // Check if paid off
        if ($this->outstanding_balance <= 0.01) {
            $this->status = 'paid_off';
            $this->outstanding_balance = 0.0;
            $this->next_payment_date = null;
        } else {
            // Calculate next payment date
            $nextDate = Carbon::parse($this->next_payment_date)->addMonth();
            $this->next_payment_date = $nextDate;
        }

        $this->save();

        // Update user's outstanding loans
        $this->user->updateOutstandingLoans();

        // TODO: Add activity logging if package is installed
        // activity()
        //     ->performedOn($this)
        //     ->withProperties([
        //         'payment_amount' => $paymentAmount,
        //         'principal' => $principal,
        //         'interest' => $interest,
        //         'new_balance' => $this->outstanding_balance,
        //     ])
        //     ->log('Loan payment processed');
    }

    /**
     * Generate amortization schedule
     */
    public function generateAmortizationSchedule(): array
    {
        $schedule = [];
        $balance = $this->original_amount;
        $monthlyRate = $this->interest_rate / 100 / 12;
        $payment = $this->monthly_payment;
        $paymentDate = Carbon::parse($this->origination_date)->addMonth();

        for ($i = 1; $i <= $this->term_months; $i++) {
            $interest = round($balance * $monthlyRate, 2);
            $principal = $payment - $interest;
            $balance = max(0, $balance - $principal);

            $schedule[] = [
                'payment_number' => $i,
                'payment_date' => $paymentDate->format('Y-m-d'),
                'payment_amount' => $payment,
                'principal' => $principal,
                'interest' => $interest,
                'balance' => $balance,
            ];

            $paymentDate->addMonth();

            if ($balance <= 0) {
                break;
            }
        }

        return $schedule;
    }

    /**
     * Check if loan is delinquent
     */
    public function isDelinquent(): bool
    {
        return $this->status === 'active' &&
               $this->next_payment_date &&
               $this->next_payment_date->isPast();
    }

    /**
     * Get days overdue
     */
    public function getDaysOverdueAttribute(): int
    {
        if (!$this->isDelinquent()) {
            return 0;
        }

        return now()->diffInDays($this->next_payment_date);
    }

    /**
     * Calculate total interest paid
     */
    public function getTotalInterestPaidAttribute(): float
    {
        return $this->payments()->sum('interest_amount');
    }

    /**
     * Calculate total principal paid
     */
    public function getTotalPrincipalPaidAttribute(): float
    {
        return $this->payments()->sum('principal_amount');
    }

    /**
     * Get remaining term in months
     */
    public function getRemainingTermAttribute(): int
    {
        $paymentsMade = $this->payments()->count();
        return max(0, $this->term_months - $paymentsMade);
    }

    /**
     * Approve loan
     */
    public function approve(int $approverId): void
    {
        if ($this->status !== 'pending') {
            throw new \Exception("Only pending loans can be approved");
        }

        $this->update([
            'status' => 'active',
            'approved_by' => $approverId,
            'approved_at' => now(),
            'next_payment_date' => Carbon::parse($this->origination_date)->addMonth(),
        ]);

        // TODO: Add activity logging if package is installed
        // activity()
        //     ->performedOn($this)
        //     ->log('Loan approved');
    }

    /**
     * Mark loan as defaulted
     */
    public function markAsDefaulted(string $reason): void
    {
        $this->update(['status' => 'defaulted']);

        // TODO: Add activity logging if package is installed
        // activity()
        //     ->performedOn($this)
        //     ->withProperties(['reason' => $reason])
        //     ->log('Loan marked as defaulted');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeDelinquent($query)
    {
        return $query->where('status', 'active')
            ->where('next_payment_date', '<', now());
    }

    public function scopeDueSoon($query, int $days = 7)
    {
        return $query->where('status', 'active')
            ->whereBetween('next_payment_date', [now(), now()->addDays($days)]);
    }

    // Accessors
    public function getProgressPercentageAttribute(): float
    {
        if ($this->original_amount == 0) {
            return 0;
        }

        return round(($this->total_paid / $this->original_amount) * 100, 2);
    }

    public function getIsActiveAttribute(): bool
    {
        return $this->status === 'active';
    }

    public function getIsPaidOffAttribute(): bool
    {
        return $this->status === 'paid_off';
    }
}
