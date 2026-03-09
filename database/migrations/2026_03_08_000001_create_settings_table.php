<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key', 128)->primary();
            $table->text('value')->nullable();
            $table->string('group', 64)->default('parameter')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
