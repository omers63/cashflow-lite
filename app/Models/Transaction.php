<?php

namespace App\Models;

use App\Services\MonthlyCollectionsService;
use Carbon\Carbon;
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
        'debit_or_credit',
        'target_account',
        'amount',
        'user_id',
        'reference',
        'status',
        'notes',
        'created_by',
        'approved_by',
        'approved_at',
        'allocation_pair_id',
        'related_transaction_id',
    ];

    protected $casts = [
        'transaction_date' => 'date',
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

    public function qualifiesForCollectionObligationTiming(): bool
    {
        return in_array($this->type, ['contribution', 'loan_repayment'], true);
    }

    /**
     * @return array{obligation_month: Carbon, obligation_label: string, due_date: Carbon, is_late: bool}|null
     */
    public function collectionObligationClassification(): ?array
    {
        if (! $this->qualifiesForCollectionObligationTiming()) {
            return null;
        }

        return app(MonthlyCollectionsService::class)->classifyCollectionTransaction($this);
    }

    /** @var array<int, true> IDs of allocation-paired transactions being deleted after their pair was reversed (skip reverse to avoid double-reverse) */
    protected static array $skipReverseAllocationPairIds = [];

    protected static function booted(): void
    {
        static::deleting(function (Transaction $transaction): void {
            if ($transaction->status !== 'complete') {
                return;
            }
            if (isset(static::$skipReverseAllocationPairIds[$transaction->id])) {
                unset(static::$skipReverseAllocationPairIds[$transaction->id]);
                return;
            }
            $transaction->reverseBalanceEffect();

            // If this is an external_import that was posted from an ExternalBankImport,
            // reset the import's imported_to_master flag and restore the external bank balance
            // so the user can safely re-import later.
            if ($transaction->type === 'external_import') {
                $import = ExternalBankImport::where('transaction_id', $transaction->id)->first();
                if ($import && $import->externalBankAccount) {
                    $import->externalBankAccount->decrement('current_balance', $import->amount);
                    $import->update([
                        'imported_to_master' => false,
                        'transaction_id' => null,
                    ]);
                }
            }

            $transaction->syncLoanStateAfterDeletion();
        });
    }

    /**
     * Keep loan aggregates in sync when a completed loan-related transaction is removed.
     */
    protected function syncLoanStateAfterDeletion(): void
    {
        if ($this->type === 'loan_repayment' && $this->reference) {
            $loan = Loan::where('loan_id', $this->reference)->first();
            if ($loan) {
                LoanPayment::where('transaction_id', $this->id)->delete();
                $loan->syncDerivedFieldsFromPayments();
            }

            return;
        }

        if ($this->type === 'loan_disbursement' && $this->reference) {
            $loan = Loan::where('loan_id', $this->reference)->first();
            if ($loan) {
                $amount = (float) $this->amount;
                $loan->refresh();
                $newOutstanding = max(0, round((float) $loan->outstanding_balance - $amount, 2));
                $loan->update(['outstanding_balance' => $newOutstanding]);
                $loan->user?->updateOutstandingLoans();
            }
        }
    }

    /**
     * Reverse the balance effect of this transaction (used when deleting a completed transaction).
     */
    public function reverseBalanceEffect(): void
    {
        DB::transaction(function () {
            $this->adjustBalance(false);
        });
    }

    // Business Logic

    /**
     * Generate unique transaction ID. Uses date, daily sequence, and random suffix to avoid
     * collisions when multiple transactions are created in the same second or same DB transaction.
     */
    public static function generateTransactionId(string $prefix = 'GEN'): string
    {
        $date = now()->format('Ymd');
        $count = static::whereDate('created_at', now())->count() + 1;
        $unique = strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));

        return sprintf('%s-%s-%04d-%s', $prefix, $date, $count, $unique);
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
            $this->adjustBalance(true);
            $this->handleBusinessEffects(true);
            $this->update(['status' => 'complete']);
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
            $reversalDC = $this->debit_or_credit === 'credit' ? 'debit' : 'credit';
            
            $reversal = static::create([
                'transaction_id' => static::generateTransactionId('REV'),
                'transaction_date' => now(),
                'type' => 'reversal',
                'debit_or_credit' => $reversalDC,
                'target_account' => $this->target_account,
                'amount' => $this->amount,
                'user_id' => $this->user_id,
                'reference' => "REVERSAL: {$this->transaction_id}",
                'status' => 'pending',
                'notes' => "Reversal of {$this->transaction_id}. Reason: {$reason}",
                'created_by' => auth()->id(),
            ]);

            $reversal->process();

            // Reverse business effects
            $this->handleBusinessEffects(false);

            // Mark original as reversed
            $this->update(['status' => 'reversed']);
        });

        return $this;
    }

    /**
     * Increment/Decrement the target account balance based on debit_or_credit.
     * @param bool $isProcessing True if we are applying the transaction, False if we are reversing it.
     */
    public function adjustBalance(bool $isProcessing): void
    {
        $amount = (float) $this->amount;
        $isCredit = $this->debit_or_credit === 'credit';

        $shouldIncrease = $isProcessing ? $isCredit : !$isCredit;
        $adjustment = $shouldIncrease ? abs($amount) : -abs($amount);

        $this->executeAdjustment($this->target_account, $adjustment);
    }

    /**
     * Whether this transaction is a credit (increases balance on target account).
     */
    public function isCredit(): bool
    {
        return $this->debit_or_credit === 'credit';
    }

    private function executeAdjustment(?string $target, float $amount): void
    {
        if (!$target) return;

        if ($target === 'master_bank' || $target === 'master_fund') {
            $master = MasterAccount::where('account_type', $target)->first();
            if ($master) {
                $master->increment('balance', $amount);
                
                // If targeting master_fund and have a user, update their fund share record too
                if ($target === 'master_fund' && $this->user) {
                    $amount > 0 ? $this->user->creditFundAccount(abs($amount)) : $this->user->debitFundAccount(abs($amount));
                }
                
                // NOTE: We don't automatically update user_bank when master_bank changes, 
                // because that's usually a separate transaction in the pair.
            }
        } elseif ($target === 'user_bank' || $target === 'user_fund') {
            if ($this->user) {
                if ($target === 'user_bank') {
                    $amount > 0 ? $this->user->creditBankAccount(abs($amount)) : $this->user->debitBankAccount(abs($amount));
                } else {
                    // This handles direct user_fund adjustments (though usually we go through master_fund)
                    $amount > 0 ? $this->user->creditFundAccount(abs($amount)) : $this->user->debitFundAccount(abs($amount));
                    
                    // IF we update user_fund directly, we MUST also update master_fund to keep them in sync
                    $masterFund = MasterAccount::where('account_type', 'master_fund')->first();
                    if ($masterFund) {
                        $masterFund->increment('balance', $amount);
                    }
                }
            }
        } elseif (str_starts_with($target, 'external_bank:')) {
            $bankId = explode(':', $target)[1] ?? null;
            if ($bankId) {
                $extBank = ExternalBankAccount::find($bankId);
                if ($extBank) {
                    $extBank->increment('current_balance', $amount);
                }
            }
        }
    }

    /**
     * Handle secondary business logic effects (e.g., updating loan balances)
     */
    private function handleBusinessEffects(bool $isProcessing): void
    {
        $amount = (float) $this->amount;
        $adjustment = $isProcessing ? $amount : -$amount;

        // 1. Loan Effects
        if (in_array($this->type, ['loan_disbursement', 'loan_repayment']) && $this->reference) {
            $loan = Loan::where('loan_id', $this->reference)->first();
            if ($loan) {
                if ($this->type === 'loan_disbursement') {
                    // Disbursement increases outstanding balance
                    $loan->increment('outstanding_balance', $adjustment);
                    if ($isProcessing) {
                        $loan->update(['status' => 'active', 'origination_date' => $this->transaction_date ?: now()]);
                    }
                } else {
                    // Repayment: settle the loan installment (LoanPayment record, balance, total_paid, next_payment_date, paid_off)
                    if ($isProcessing) {
                        $loan->processPayment($this);
                    } else {
                        // Reversal: reverse the payment effect (handled by processPayment's logic is in one direction; for reverse we'd need a separate path if we ever reverse loan_repayment)
                        $loan->decrement('outstanding_balance', $adjustment);
                        $loan->increment('total_paid', $adjustment);
                        if ($loan->outstanding_balance <= 0) {
                            $loan->update(['status' => 'paid_off']);
                        }
                    }
                }
            }
        }

        // 2. Membership Fee / User Activation
        if ($this->type === 'membership_fee' && $this->user && $isProcessing) {
            if ($this->user->status === 'pending') {
                $this->user->activate();
            }
        }
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
