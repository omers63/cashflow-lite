<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exceptions', function (Blueprint $table) {
            $table->id();
            $table->string('exception_id')->unique();
            $table->enum('type', [
                'duplicate_import',
                'balance_mismatch',
                'negative_balance',
                'loan_payment_mismatch',
                'missing_transaction',
                'fund_account_negative',
                'other'
            ]);
            $table->enum('severity', ['low', 'medium', 'high', 'critical']);
            $table->text('description');
            $table->json('affected_accounts')->nullable();
            $table->decimal('variance_amount', 15, 2)->nullable();
            $table->foreignId('related_transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->foreignId('related_reconciliation_id')->nullable()->constrained('reconciliations')->nullOnDelete();
            $table->enum('status', ['open', 'under_investigation', 'resolved', 'closed'])->default('open');
            $table->text('resolution_steps')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->integer('sla_hours')->comment('Hours to resolve based on severity');
            $table->timestamp('sla_deadline');
            $table->boolean('sla_breached')->default(false);
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('exception_id');
            $table->index('type');
            $table->index('severity');
            $table->index('status');
            $table->index('sla_deadline');
            $table->index(['status', 'severity']);
            $table->index(['assigned_to', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exceptions');
    }
};
