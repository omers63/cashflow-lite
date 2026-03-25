<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
        'member_id',
        'origination_date',
        'fully_disbursed_date',
        'original_amount',
        'interest_rate',
        'term_months',
        'monthly_payment',
        'installment_amount',
        'maturity_fund_balance',
        'total_paid',
        'outstanding_balance',
        'status',
        'is_emergency',
        'next_payment_date',
        'maturity_date',
        'approved_by',
        'approved_at',
        'notes',
    ];

    protected $casts = [
        'origination_date' => 'date',
        'fully_disbursed_date' => 'date',
        'original_amount' => 'decimal:2',
        'interest_rate' => 'decimal:2',
        'monthly_payment' => 'decimal:2',
        'installment_amount' => 'decimal:2',
        'maturity_fund_balance' => 'decimal:2',
        'total_paid' => 'decimal:2',
        'outstanding_balance' => 'decimal:2',
        'next_payment_date' => 'date',
        'maturity_date' => 'date',
        'approved_at' => 'datetime',
        'is_emergency' => 'boolean',
    ];

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(LoanPayment::class);
    }

    public function disbursements(): HasMany
    {
        return $this->hasMany(LoanDisbursement::class, 'loan_id');
    }

    /** Transactions linked to this loan (disbursements and repayments; reference = loan_id). */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'reference', 'loan_id')
            ->whereIn('type', ['loan_disbursement', 'loan_repayment']);
    }

    // Business Logic

    /**
     * Generate unique loan ID
     */
    public static function generateLoanId(): string
    {
        $date = now()->format('Ymd');
        $count = static::withTrashed()->whereDate('created_at', now())->count() + 1;
        $id = sprintf('LOAN-%s-%03d', $date, $count);

        while (static::withTrashed()->where('loan_id', $id)->exists()) {
            $count++;
            $id = sprintf('LOAN-%s-%03d', $date, $count);
        }

        return $id;
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
        $newBalance = (float) max(0, $this->outstanding_balance - $principal);
        $this->total_paid = (float) ($this->total_paid + $paymentAmount);
        $this->outstanding_balance = $newBalance;

        // Check if paid off: either balance cleared or total paid covers 50% + 16% of original (payoff rule)
        $payoffThreshold = (float) $this->original_amount * (0.50 + 0.16); // 66%
        if ($this->outstanding_balance <= 0.01 || $this->total_paid >= $payoffThreshold - 0.01) {
            $this->status = 'paid_off';
            $this->outstanding_balance = 0.0;
            $this->next_payment_date = null;
        } else {
            // Only move date if we processed at least a full installment
            if ($paymentAmount >= ($this->installment_amount * 0.9)) {
                $this->next_payment_date = $this->next_payment_date ? Carbon::parse($this->next_payment_date)->addMonthNoOverflow() : null;
            }
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
     * Generate amortization schedule using the tier installment amount.
     * Payment dates fall on the cycle due day (e.g. 5th). First repayment is the cycle
     * after the first contribution due date on or after the last (full) disbursement.
     */
    public function generateAmortizationSchedule(): array
    {
        $schedule = [];
        $balance = (float) $this->original_amount;
        $monthlyRate = (float) $this->interest_rate / 100 / 12;
        $payment = (float) ($this->installment_amount ?? $this->monthly_payment);

        $dueDay = Setting::getInt('collections_due_day', 5);
        $dueDay = max(1, min(28, $dueDay));

        if ($this->next_payment_date) {
            $paymentDate = Carbon::parse($this->next_payment_date)->day($dueDay)->startOfDay();
        } else {
            $disbursedAt = $this->fully_disbursed_date
                ? Carbon::parse($this->fully_disbursed_date)
                : ($this->disbursements()->exists()
                    ? Carbon::parse($this->disbursements()->max('disbursement_date'))
                    : Carbon::parse($this->origination_date));
            $paymentDate = static::firstRepaymentDateFromDisbursedAt($disbursedAt)->day($dueDay)->startOfDay();
        }

        for ($i = 1; $i <= $this->term_months; $i++) {
            $interest = round($balance * $monthlyRate, 2);
            $principal = $payment - $interest;
            if ($principal > $balance) {
                $principal = $balance;
            }
            $balance = max(0, round($balance - $principal, 2));

            $schedule[] = [
                'payment_number' => $i,
                'payment_date' => $paymentDate->format('Y-m-d'),
                'payment_amount' => $payment,
                'principal' => $principal,
                'interest' => $interest,
                'balance' => $balance,
                'post_date' => null,
            ];

            $paymentDate = $paymentDate->copy()->addMonthNoOverflow()->day($dueDay)->startOfDay();

            if ($balance <= 0) {
                break;
            }
        }

        $payments = $this->payments()->orderBy('payment_date')->get();
        foreach ($payments as $idx => $loanPayment) {
            if (isset($schedule[$idx])) {
                $schedule[$idx]['post_date'] = $loanPayment->payment_date?->format('Y-m-d');
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
     * Recompute outstanding balance from original amount and payment history.
     * outstanding = original_amount - sum(principal_amount from payments).
     */
    public function recomputeOutstandingBalanceFromPayments(): float
    {
        $principalPaid = (float) $this->payments()->sum('principal_amount');
        return max(0, (float) $this->original_amount - $principalPaid);
    }

    /**
     * Rebuild loan totals and next payment from remaining loan_payments (e.g. after deleting a repayment transaction).
     * Mirrors processPayment rules: next due = first schedule date + one month per installment ≥ 90% of scheduled payment.
     */
    public function syncDerivedFieldsFromPayments(): void
    {
        $this->refresh();

        $principalPaid = (float) $this->payments()->sum('principal_amount');
        $totalPaid = (float) $this->payments()->sum('payment_amount');
        $outstanding = max(0, round((float) $this->original_amount - $principalPaid, 2));

        $installment = (float) ($this->installment_amount ?? $this->monthly_payment ?? 0);
        $payments = $this->payments()->orderBy('payment_date')->orderBy('id')->get();
        $fullInstallmentCount = $installment > 0.01
            ? $payments->filter(fn (LoanPayment $p) => (float) $p->payment_amount >= $installment * 0.9)->count()
            : $payments->count();

        $disbursedAt = $this->fully_disbursed_date
            ? Carbon::parse($this->fully_disbursed_date)
            : ($this->disbursements()->exists()
                ? Carbon::parse($this->disbursements()->max('disbursement_date'))
                : Carbon::parse($this->origination_date));
        $firstRepayment = static::firstRepaymentDateFromDisbursedAt($disbursedAt);

        $payoffThreshold = (float) $this->original_amount * (0.50 + 0.16);

        $hasPaidOffDate = Schema::hasColumn($this->getTable(), 'paid_off_date');

        if ($outstanding <= 0.01 || $totalPaid >= $payoffThreshold - 0.01) {
            $status = 'paid_off';
            $outstanding = 0.0;
            $nextPayment = null;
        } else {
            $status = $this->status === 'paid_off' ? 'active' : $this->status;
            if ($status === 'pending' && $this->payments()->exists()) {
                $status = 'active';
            }
            $nextPayment = $firstRepayment->copy()->addMonthsNoOverflow($fullInstallmentCount);
        }

        $payload = [
            'outstanding_balance' => $outstanding,
            'total_paid' => $totalPaid,
            'next_payment_date' => $nextPayment,
            'status' => $status,
        ];
        if ($hasPaidOffDate) {
            $payload['paid_off_date'] = ($outstanding <= 0.01 || $totalPaid >= $payoffThreshold - 0.01)
                ? ($this->getAttribute('paid_off_date') ?: now()->toDateString())
                : null;
        }

        $this->forceFill($payload)->save();

        $this->user?->updateOutstandingLoans();
    }

    /**
     * Delete all loan payment rows and recompute outstanding, total paid, next due, and status.
     * Does not remove ledger transactions — adjust those separately if books must match.
     */
    public function resetPaymentHistory(): void
    {
        DB::transaction(function (): void {
            $this->payments()->delete();
            $this->refresh();
            $this->syncDerivedFieldsFromPayments();
        });
    }

    /**
     * Delete all planned disbursement rows and recompute fully disbursed / first repayment / maturity from origination.
     * Does not reverse posted disbursement transactions.
     */
    public function clearPlannedDisbursements(): void
    {
        DB::transaction(function (): void {
            $this->disbursements()->delete();
            $this->refresh();
            $this->updateRepaymentAndMaturityDatesFromDisbursements();
            $this->refresh();
            $this->syncDerivedFieldsFromPayments();
        });
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
     * Set fully_disbursed_date, next_payment_date, and maturity_date from the loan's
     * disbursements (or origination_date if none). Use after creating a loan with a
     * disbursement schedule so the maturity date shows before approval.
     */
    public function updateRepaymentAndMaturityDatesFromDisbursements(): void
    {
        $disbursedAt = $this->disbursements()->exists()
            ? Carbon::parse($this->disbursements()->max('disbursement_date'))
            : Carbon::parse($this->origination_date);

        $firstRepaymentDate = static::firstRepaymentDateFromDisbursedAt($disbursedAt);
        $termMonths = (int) $this->term_months;
        $maturityDate = $termMonths > 0
            ? $firstRepaymentDate->copy()->addMonthsNoOverflow($termMonths - 1)
            : null;

        $this->update([
            'fully_disbursed_date' => $disbursedAt->toDateString(),
            'next_payment_date' => $firstRepaymentDate,
            'maturity_date' => $maturityDate,
        ]);
    }

    /**
     * Compute the first repayment date: one full cycle after the first collection due date
     * on or after the given date (e.g. fully disbursed date). Uses collections_due_day.
     */
    public static function firstRepaymentDateFromDisbursedAt(Carbon $disbursedAt): Carbon
    {
        $dueDay = Setting::getInt('collections_due_day', 5);
        $dueDay = max(1, min(28, $dueDay));
        $firstDueOnOrAfter = $disbursedAt->copy()->day($dueDay)->startOfDay();
        if ($disbursedAt->greaterThan($firstDueOnOrAfter)) {
            $firstDueOnOrAfter->addMonthNoOverflow();
        }
        return $firstDueOnOrAfter->copy()->addMonthNoOverflow();
    }

    /**
     * Approve loan — sets tier-based installment/maturity values.
     * Disbursement transactions are created by LoanService.
     *
     * @param  Carbon|null  $fullyDisbursedAt  When the loan was/will be fully disbursed; repayment starts the cycle after this. If null, uses now().
     */
    public function approve(int $approverId, ?Carbon $fullyDisbursedAt = null): void
    {
        if ($this->status !== 'pending') {
            throw new \Exception("Only pending loans can be approved");
        }

        $tier = Member::loanTierFor((float) $this->original_amount);
        $disbursedAt = $fullyDisbursedAt ?? Carbon::now();
        $firstRepaymentDate = static::firstRepaymentDateFromDisbursedAt($disbursedAt);
        $termMonths = (int) $this->term_months;
        $maturityDate = $termMonths > 0
            ? $firstRepaymentDate->copy()->addMonthsNoOverflow($termMonths - 1)
            : null;

        $this->update([
            'status' => 'active',
            'approved_by' => $approverId,
            'approved_at' => now(),
            'next_payment_date' => $firstRepaymentDate,
            'maturity_date' => $maturityDate,
            'installment_amount' => $tier['installment_amount'] ?? $this->monthly_payment,
            'maturity_fund_balance' => $tier['maturity_balance'] ?? 0,
            'monthly_payment' => $tier['installment_amount'] ?? $this->monthly_payment,
        ]);

        activity()
            ->performedOn($this)
            ->log('Loan approved and disbursed');
    }

    /**
     * Check if the loan has matured: outstanding balance is paid AND the member's
     * fund account balance has reached the tier's maturity_fund_balance threshold.
     */
    public function isMatured(): bool
    {
        if ((float) $this->outstanding_balance > 0.01) {
            return false;
        }
        if (! $this->member) {
            return (float) $this->outstanding_balance <= 0.01;
        }
        $target = (float) ($this->maturity_fund_balance ?? 0);
        return (float) $this->member->fund_account_balance >= $target;
    }

    /** Whether this is the member's first loan ever. */
    public function isFirstLoanForMember(): bool
    {
        if (! $this->member_id) {
            return true;
        }
        return static::where('member_id', $this->member_id)
            ->where('id', '!=', $this->id)
            ->doesntExist();
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

    /**
     * Pending loans ordered by approval priority:
     *   1. Emergency requests first
     *   2. First-time requesters before repeat borrowers
     *   3. Earliest request date/time
     */
    public function scopePendingPrioritized($query)
    {
        return $query->where('status', 'pending')
            ->orderByDesc('is_emergency')
            ->orderByRaw(
                '(SELECT COUNT(*) FROM loans AS l2 WHERE l2.member_id = loans.member_id AND l2.id != loans.id) ASC'
            )
            ->orderBy('created_at', 'asc');
    }

    /**
     * Loan queue: qualified and pending/unapproved loans ordered by loan tier (original_amount)
     * then submission date (created_at). Used for projected master fund (pending disbursements).
     */
    public function scopeLoanQueue($query)
    {
        return $query->where('status', 'pending')
            ->orderBy('original_amount', 'asc')
            ->orderBy('created_at', 'asc');
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
