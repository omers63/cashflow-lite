<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExternalBankImportBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'external_bank_account_id',
        'source_type',
        'source_name',
        'rows_total',
        'rows_new',
        'rows_duplicates',
        'rows_posted',
        'created_by',
    ];

    public function externalBankAccount(): BelongsTo
    {
        return $this->belongsTo(ExternalBankAccount::class);
    }

    public function imports(): HasMany
    {
        return $this->hasMany(ExternalBankImport::class, 'external_bank_import_batch_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

