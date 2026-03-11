<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->enum('type', [
                'external_import',
                'master_to_user_bank',
                'contribution',
                'loan_repayment',
                'loan_disbursement',
                'adjustment',
                'credit',
                'debit'
            ])->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->enum('type', [
                'external_import',
                'master_to_user_bank',
                'contribution',
                'loan_repayment',
                'loan_disbursement',
                'adjustment'
            ])->change();
        });
    }
};
