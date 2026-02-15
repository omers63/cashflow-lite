<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

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
