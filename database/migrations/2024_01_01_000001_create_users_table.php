<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('user_code')->unique()->comment('USER001, USER002, etc.');
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('phone')->nullable();
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');
            $table->decimal('bank_account_balance', 15, 2)->default(0)->comment('User Bank Account');
            $table->decimal('fund_account_balance', 15, 2)->default(0)->comment('User Fund Account');
            $table->decimal('outstanding_loans', 15, 2)->default(0)->comment('Total outstanding loan balance');
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('user_code');
            $table->index('status');
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
