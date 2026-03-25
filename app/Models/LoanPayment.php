<?php

namespace App\Models;

use App\Services\MonthlyCollectionsService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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

    /**
     * @return array{obligation_month: \Carbon\Carbon, obligation_label: string, due_date: \Carbon\Carbon, is_late: bool}|null
     */
    public function collectionObligationClassification(): ?array
    {
        $txn = $this->transaction;
        if ($txn === null) {
            return null;
        }

        return app(MonthlyCollectionsService::class)->classifyCollectionTransaction($txn);
    }
}
