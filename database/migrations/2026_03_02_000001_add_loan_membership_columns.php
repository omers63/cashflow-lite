<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->date('membership_date')->nullable()->after('parent_id');
        });

        // Back-fill existing members with their created_at date
        \App\Models\Member::query()->whereNull('membership_date')->each(function ($m) {
            $m->update(['membership_date' => $m->created_at?->toDateString() ?? now()->toDateString()]);
        });

        Schema::table('loans', function (Blueprint $table) {
            $table->foreignId('member_id')->nullable()->after('user_id')->constrained('members')->nullOnDelete();
            $table->boolean('is_emergency')->default(false)->after('status');
            $table->decimal('installment_amount', 15, 2)->nullable()->after('monthly_payment');
            $table->decimal('maturity_fund_balance', 15, 2)->nullable()->after('installment_amount');
            $table->index('member_id');
        });

        // Back-fill existing loans with member_id from user → member
        \App\Models\Loan::query()->whereNull('member_id')->each(function ($loan) {
            $member = \App\Models\Member::where('user_id', $loan->user_id)->first();
            if ($member) {
                $loan->update(['member_id' => $member->id]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropForeign(['member_id']);
            $table->dropColumn(['member_id', 'is_emergency', 'installment_amount', 'maturity_fund_balance']);
        });

        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn('membership_date');
        });
    }
};
