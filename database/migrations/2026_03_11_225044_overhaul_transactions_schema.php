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
            $table->dropColumn(['from_account', 'to_account']);
            $table->string('target_account')->after('type')->nullable();
            $table->foreignId('related_transaction_id')->nullable()->after('allocation_pair_id')->constrained('transactions')->nullOnDelete();
            
            // Re-define type to be strictly credit/debit for the new architecture
            // Note: Since changing enum in SQLite can be tricky, we'll use a string if it's easier or change it via change()
            $table->string('type')->change(); 
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('from_account')->nullable();
            $table->string('to_account')->nullable();
            $table->dropColumn(['target_account', 'related_transaction_id']);
        });
    }
};
