<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class Transaction extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'transaction_id',
        'transaction_date',
        'type',
        'from_account',
        'to_account',
        'amount',
        'user_id',
        'reference',
        'status',
        'notes',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'transaction_date' => 'datetime',
        'amount' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function loanPayment()
    {
        return $this->hasOne(LoanPayment::class);
    }

    protected static function booted(): void
    {
        static::deleting(function (Transaction $transaction): void {
            if ($transaction->status !== 'complete') {
                return;
            }
            $transaction->reverseBalanceEffect();
        });
    }

    /**
     * Reverse the balance effect of this transaction (used when deleting a completed transaction).
     */
    public function reverseBalanceEffect(): void
    {
        DB::transaction(function () {
            switch ($this->type) {
                case 'external_import':
                    $this->reverseExternalImport();
                    break;
                case 'master_to_user_bank':
                    $this->reverseMasterToUserBank();
                    break;
                case 'contribution':
                    $this->reverseContribution();
                    break;
                case 'loan_repayment':
                    $this->reverseLoanRepayment();
                    break;
                case 'loan_disbursement':
                    $this->reverseLoanDisbursement();
                    break;
                case 'adjustment':
                    // No balance effect to reverse
                    break;
            }
        });
    }

    private function reverseExternalImport(): void
    {
        $masterBank = MasterAccount::where('account_type', 'master_bank')->first();
        if ($masterBank) {
            $masterBank->decrement('balance', $this->amount);
        }
    }

    private function reverseMasterToUserBank(): void
    {
        $masterBank = MasterAccount::where('account_type', 'master_bank')->first();
        if ($masterBank) {
            $masterBank->increment('balance', $this->amount);
        }
        if ($this->user) {
            $this->user->debitBankAccount($this->amount);
        }
    }

    private function reverseContribution(): void
    {
        if ($this->user) {
            $this->user->creditBankAccount($this->amount);
            $this->user->debitFundAccount($this->amount);
        }
        $masterFund = MasterAccount::where('account_type', 'master_fund')->first();
        if ($masterFund) {
            $masterFund->decrement('balance', $this->amount);
        }
    }

    private function reverseLoanRepayment(): void
    {
        if ($this->user) {
            $this->user->creditBankAccount($this->amount);
            $this->user->debitFundAccount($this->amount);
        }
        $masterFund = MasterAccount::where('account_type', 'master_fund')->first();
        if ($masterFund) {
            $masterFund->decrement('balance', $this->amount);
        }
        // Note: Loan outstanding_balance is not auto-reversed; reconcile manually if needed.
    }

    private function reverseLoanDisbursement(): void
    {
        $masterFund = MasterAccount::where('account_type', 'master_fund')->first();
        if ($masterFund) {
            $masterFund->increment('balance', $this->amount);
        }
        if ($this->user) {
            $this->user->debitBankAccount($this->amount);
            $this->user->updateOutstandingLoans();
        }
    }

    // Business Logic

    /**
     * Generate unique transaction ID
     */
    public static function generateTransactionId(string $prefix = 'GEN'): string
    {
        $date = now()->format('Ymd');
        $count = static::whereDate('created_at', now())->count() + 1;
        
        return sprintf('%s-%s-%04d', $prefix, $date, $count);
    }

    /**
     * Process transaction and update accounts
     */
    public function process(): void
    {
        if ($this->status !== 'pending') {
            throw new \Exception("Only pending transactions can be processed");
        }

        DB::transaction(function () {
            switch ($this->type) {
                case 'external_import':
                    $this->processExternalImport();
                    break;
                case 'master_to_user_bank':
                    $this->processMasterToUserBank();
                    break;
                case 'contribution':
                    $this->processContribution();
                    break;
                case 'loan_repayment':
                    $this->processLoanRepayment();
                    break;
                case 'loan_disbursement':
                    $this->processLoanDisbursement();
                    break;
                case 'adjustment':
                    $this->processAdjustment();
                    break;
            }

            $this->update(['status' => 'complete']);

            activity()
                ->performedOn($this)
                ->log('Transaction processed successfully');
        });
    }

    /**
     * Reverse transaction
     */
    public function reverse(string $reason): self
    {
        if ($this->status !== 'complete') {
            throw new \Exception("Only completed transactions can be reversed");
        }

        DB::transaction(function () use ($reason) {
            // Create reversal transaction
            $reversal = static::create([
                'transaction_id' => static::generateTransactionId('REV'),
                'transaction_date' => now(),
                'type' => $this->type,
                'from_account' => $this->to_account, // Swap
                'to_account' => $this->from_account, // Swap
                'amount' => $this->amount,
                'user_id' => $this->user_id,
                'reference' => "REVERSAL: {$this->transaction_id}",
                'status' => 'pending',
                'notes' => "Reversal of {$this->transaction_id}. Reason: {$reason}",
                'created_by' => auth()->id(),
            ]);

            $reversal->process();

            // Mark original as reversed
            $this->update(['status' => 'reversed']);

            activity()
                ->performedOn($this)
                ->withProperties(['reason' => $reason, 'reversal_id' => $reversal->id])
                ->log('Transaction reversed');
        });

        return $this;
    }

    // Private processing methods
    private function processExternalImport(): void
    {
        $masterBank = MasterAccount::where('account_type', 'master_bank')->first();
        $masterBank->increment('balance', $this->amount);
    }

    private function processMasterToUserBank(): void
    {
        $masterBank = MasterAccount::where('account_type', 'master_bank')->first();
        
        if ($masterBank->balance < $this->amount) {
            throw new \Exception("Insufficient master bank balance");
        }

        $masterBank->decrement('balance', $this->amount);
        $this->user->creditBankAccount($this->amount);
    }

    private function processContribution(): void
    {
        $this->user->debitBankAccount($this->amount);
        $this->user->creditFundAccount($this->amount);
        
        $masterFund = MasterAccount::where('account_type', 'master_fund')->first();
        $masterFund->increment('balance', $this->amount);
    }

    private function processLoanRepayment(): void
    {
        $this->user->debitBankAccount($this->amount);
        $this->user->creditFundAccount($this->amount);
        
        $masterFund = MasterAccount::where('account_type', 'master_fund')->first();
        $masterFund->increment('balance', $this->amount);

        // Update loan (handled in Loan model)
        if ($this->reference) {
            $loan = Loan::where('loan_id', $this->reference)->first();
            if ($loan) {
                $loan->processPayment($this);
            }
        }
    }

    private function processLoanDisbursement(): void
    {
        $masterFund = MasterAccount::where('account_type', 'master_fund')->first();
        
        if ($masterFund->balance < $this->amount) {
            throw new \Exception("Insufficient master fund balance");
        }

        $masterFund->decrement('balance', $this->amount);
        $this->user->creditBankAccount($this->amount);
        $this->user->updateOutstandingLoans();
    }

    private function processAdjustment(): void
    {
        // Adjustments are handled manually with proper documentation
        activity()
            ->performedOn($this)
            ->withProperties([
                'from' => $this->from_account,
                'to' => $this->to_account,
                'amount' => $this->amount,
            ])
            ->log('Manual adjustment processed');
    }

    // Scopes
    public function scopeComplete($query)
    {
        return $query->where('status', 'complete');
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('transaction_date', [$startDate, $endDate]);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('transaction_date', now());
    }

    // Accessors
    public function getIsReversedAttribute(): bool
    {
        return $this->status === 'reversed';
    }

    public function getIsCompleteAttribute(): bool
    {
        return $this->status === 'complete';
    }

    public function getFormattedAmountAttribute(): string
    {
        return '$' . number_format($this->amount, 2);
    }
}
