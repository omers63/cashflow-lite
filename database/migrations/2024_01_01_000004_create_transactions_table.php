<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_id')->unique()->comment('GEN-20240215-0001');
            $table->dateTime('transaction_date');
            $table->enum('type', [
                'external_import',
                'master_to_user_bank',
                'contribution',
                'loan_repayment',
                'loan_disbursement',
                'adjustment'
            ]);
            $table->string('from_account');
            $table->string('to_account');
            $table->decimal('amount', 15, 2);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference')->nullable()->comment('External Ref ID, Loan ID, etc.');
            $table->enum('status', ['pending', 'complete', 'failed', 'reversed'])->default('complete');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('transaction_date');
            $table->index('type');
            $table->index('status');
            $table->index(['user_id', 'type']);
            $table->index(['transaction_date', 'type']);
            $table->index('reference');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
