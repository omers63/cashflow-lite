<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->date('fully_disbursed_date')->nullable()->after('origination_date');
        });

        Schema::create('loan_disbursements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->date('disbursement_date');
            $table->decimal('amount', 15, 2);
            $table->foreignId('fund_debit_transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->foreignId('user_credit_transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->timestamps();

            $table->index(['loan_id', 'disbursement_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_disbursements');
        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn('fully_disbursed_date');
        });
    }
};
