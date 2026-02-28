<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('members')->orderBy('id')->chunk(100, function ($members) {
            foreach ($members as $member) {
                $user = DB::table('users')->where('id', $member->user_id)->first();
                if ($user) {
                    DB::table('members')->where('id', $member->id)->update([
                        'bank_account_balance' => $user->bank_account_balance ?? 0,
                        'fund_account_balance' => $user->fund_account_balance ?? 0,
                        'outstanding_loans' => $user->outstanding_loans ?? 0,
                    ]);
                }
            }
        });
    }

    public function down(): void
    {
        // Data copy is not reversed; remove columns migration will run.
    }
};
