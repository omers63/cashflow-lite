<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('bank_name');
            $table->string('account_number')->unique();
            $table->string('account_type')->default('checking'); // checking, savings
            $table->decimal('current_balance', 15, 2)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->enum('status', ['active', 'inactive', 'closed'])->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('bank_name');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_bank_accounts');
    }
};
