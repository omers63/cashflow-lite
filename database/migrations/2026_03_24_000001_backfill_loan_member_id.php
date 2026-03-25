<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Set loans.member_id from members.id where user_id matches and member_id is still null.
     * Uses the query builder so model events/observers do not run.
     */
    public function up(): void
    {
        DB::table('loans')
            ->whereNull('member_id')
            ->whereNotNull('user_id')
            ->orderBy('id')
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    $memberId = DB::table('members')
                        ->where('user_id', $row->user_id)
                        ->value('id');
                    if ($memberId) {
                        DB::table('loans')->where('id', $row->id)->update(['member_id' => $memberId]);
                    }
                }
            });
    }

    public function down(): void
    {
        // Data fix — no safe automatic rollback.
    }
};
