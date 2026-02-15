<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('master_accounts', function (Blueprint $table) {
            $table->id();
            $table->enum('account_type', ['master_bank', 'master_fund'])->unique();
            $table->decimal('balance', 15, 2)->default(0);
            $table->decimal('opening_balance', 15, 2)->default(0);
            $table->date('balance_date')->default(now());
            $table->timestamps();
            
            $table->index('account_type');
            $table->index('balance_date');
        });

        // Insert default master accounts
        DB::table('master_accounts')->insert([
            [
                'account_type' => 'master_bank',
                'balance' => 0,
                'opening_balance' => 0,
                'balance_date' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'account_type' => 'master_fund',
                'balance' => 0,
                'opening_balance' => 0,
                'balance_date' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('master_accounts');
    }
};
