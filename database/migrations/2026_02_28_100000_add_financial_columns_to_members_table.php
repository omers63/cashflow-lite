<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->decimal('bank_account_balance', 15, 2)->default(0)->after('parent_id')->comment('User Bank Account');
            $table->decimal('fund_account_balance', 15, 2)->default(0)->after('bank_account_balance')->comment('User Fund Account');
            $table->decimal('outstanding_loans', 15, 2)->default(0)->after('fund_account_balance')->comment('Total outstanding loan balance');
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn(['bank_account_balance', 'fund_account_balance', 'outstanding_loans']);
        });
    }
};
