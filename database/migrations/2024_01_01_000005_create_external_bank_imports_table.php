<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_bank_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('external_bank_account_id')->constrained()->cascadeOnDelete();
            $table->date('import_date');
            $table->dateTime('transaction_date');
            $table->string('external_ref_id')->comment('From bank statement');
            $table->decimal('amount', 15, 2);
            $table->text('description')->nullable();
            $table->boolean('is_duplicate')->default(false);
            $table->boolean('imported_to_master')->default(false);
            $table->foreignId('transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('external_ref_id');
            $table->index('import_date');
            $table->index('is_duplicate');
            $table->index('imported_to_master');
            $table->index(['external_bank_account_id', 'import_date']);
            $table->unique(['external_bank_account_id', 'external_ref_id'], 'unique_external_ref');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_bank_imports');
    }
};
