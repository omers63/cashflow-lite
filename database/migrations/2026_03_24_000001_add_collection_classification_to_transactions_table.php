<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->date('collection_obligation_month')->nullable()->after('related_transaction_id');
            $table->date('collection_period_due_date')->nullable()->after('collection_obligation_month');
            $table->boolean('collection_is_late')->nullable()->after('collection_period_due_date');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn([
                'collection_obligation_month',
                'collection_period_due_date',
                'collection_is_late',
            ]);
        });
    }
};
