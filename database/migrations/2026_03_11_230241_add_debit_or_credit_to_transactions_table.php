<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('debit_or_credit', 6)->after('type')->default('credit');
        });

        $creditTypes = ['credit', 'external_import', 'contribution', 'loan_repayment', 'import_deposit', 'allocation_from_parent'];

        DB::table('transactions')->get()->each(function ($tx) use ($creditTypes) {
            $dc = in_array($tx->type, $creditTypes) ? 'credit' : 'debit';
            if ($tx->type === 'adjustment') {
                $dc = ((float) $tx->amount) >= 0 ? 'credit' : 'debit';
            }
            DB::table('transactions')->where('id', $tx->id)->update(['debit_or_credit' => $dc]);
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('debit_or_credit');
        });
    }
};
