<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reconciliations', function (Blueprint $table) {
            $table->id();
            $table->date('reconciliation_date');
            $table->enum('type', ['daily', 'monthly', 'manual'])->default('daily');
            $table->json('check_results')->comment('Results of all 7 checks');
            $table->boolean('all_passed')->default(false);
            $table->integer('checks_passed')->default(0);
            $table->integer('checks_failed')->default(0);
            $table->decimal('total_variance', 15, 2)->default(0);
            $table->enum('status', ['pending', 'complete', 'failed', 'under_review'])->default('pending');
            $table->text('notes')->nullable();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            
            $table->index('reconciliation_date');
            $table->index('type');
            $table->index('status');
            $table->index(['reconciliation_date', 'type']);
            $table->unique(['reconciliation_date', 'type'], 'unique_reconciliation');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reconciliations');
    }
};
