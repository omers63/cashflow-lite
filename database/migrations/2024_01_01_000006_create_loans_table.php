<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->string('loan_id')->unique()->comment('LOAN-20240215-001');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('origination_date');
            $table->decimal('original_amount', 15, 2);
            $table->decimal('interest_rate', 5, 2)->comment('Annual percentage rate');
            $table->integer('term_months');
            $table->decimal('monthly_payment', 15, 2);
            $table->decimal('total_paid', 15, 2)->default(0);
            $table->decimal('outstanding_balance', 15, 2);
            $table->enum('status', ['pending', 'active', 'paid_off', 'defaulted', 'cancelled'])->default('active');
            $table->date('next_payment_date')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('loan_id');
            $table->index('user_id');
            $table->index('status');
            $table->index('next_payment_date');
            $table->index(['user_id', 'status']);
            $table->index(['status', 'next_payment_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};
