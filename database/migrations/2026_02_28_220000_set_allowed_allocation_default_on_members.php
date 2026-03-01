<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Seed any existing NULLs with the default before making it required.
        DB::table('members')->whereNull('allowed_allocation')->update(['allowed_allocation' => 500]);

        Schema::table('members', function (Blueprint $table) {
            $table->decimal('allowed_allocation', 15, 2)->default(500)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->decimal('allowed_allocation', 15, 2)->nullable()->default(null)->change();
        });
    }
};
