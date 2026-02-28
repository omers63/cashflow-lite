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

    protected static function booted(): void
    {
        static::deleting(function (ExternalBankImport $import): void {
            $account = $import->externalBankAccount;
            if ($account && $import->imported_to_master) {
                $account->decrement('current_balance', $import->amount);
            }
        });
    }

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

    /**
     * Post this import to the Master Bank Account: create a Transaction, process it, and mark import as imported.
     * Call this when the user explicitly chooses "Import to Master" (do not call automatically on create).
     */
    public function postToMasterBank(): void
    {
        if ($this->imported_to_master || $this->is_duplicate) {
            return;
        }

        $bank = $this->externalBankAccount;
        if (! $bank) {
            throw new \RuntimeException('External bank account not found.');
        }

        $transaction = Transaction::create([
            'transaction_id' => Transaction::generateTransactionId('EXT'),
            'transaction_date' => $this->transaction_date,
            'type' => 'external_import',
            'from_account' => $bank->bank_name ?? 'External Bank',
            'to_account' => 'Master Bank Account',
            'amount' => $this->amount,
            'reference' => $this->external_ref_id,
            'status' => 'pending',
            'notes' => $this->description,
            'created_by' => auth()->id(),
        ]);

        $transaction->process();

        $this->update([
            'imported_to_master' => true,
            'transaction_id' => $transaction->id,
        ]);

        $bank->increment('current_balance', $this->amount);
    }
}
