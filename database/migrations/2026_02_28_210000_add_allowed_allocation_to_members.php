<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->decimal('allowed_allocation', 15, 2)->nullable()->after('outstanding_loans')
                ->comment('Max amount the parent may allocate to this dependant per transaction. Null = no limit.');
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn('allowed_allocation');
        });
    }
};
