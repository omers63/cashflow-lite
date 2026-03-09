<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('balance_snapshots', function (Blueprint $table) {
            $table->id();
            $table->date('snapshot_date')->comment('Period end date (e.g. last day of month)');
            $table->string('period', 20)->default('monthly')->comment('monthly, etc.');
            $table->decimal('master_bank', 15, 2)->default(0);
            $table->decimal('master_fund', 15, 2)->default(0);
            $table->decimal('external_banks_total', 15, 2)->default(0);
            $table->decimal('member_banks_total', 15, 2)->default(0);
            $table->decimal('member_funds_total', 15, 2)->default(0);
            $table->decimal('outstanding_loans_total', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('snapshot_date');
            $table->index(['snapshot_date', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('balance_snapshots');
    }
};
