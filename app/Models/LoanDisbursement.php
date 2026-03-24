<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanDisbursement extends Model
{
    protected $fillable = [
        'loan_id',
        'disbursement_date',
        'amount',
        'fund_debit_transaction_id',
        'user_credit_transaction_id',
    ];

    protected $casts = [
        'disbursement_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function fundDebitTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'fund_debit_transaction_id');
    }

    public function userCreditTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'user_credit_transaction_id');
    }
}
