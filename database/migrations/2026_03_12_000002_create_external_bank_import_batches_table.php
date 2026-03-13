<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_bank_import_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('external_bank_account_id')->constrained()->cascadeOnDelete();
            $table->string('source_type', 20); // file | manual
            $table->string('source_name')->nullable(); // filename or label
            $table->unsignedInteger('rows_total')->default(0);
            $table->unsignedInteger('rows_new')->default(0);
            $table->unsignedInteger('rows_duplicates')->default(0);
            $table->unsignedInteger('rows_posted')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('external_bank_imports', function (Blueprint $table) {
            $table->foreignId('external_bank_import_batch_id')
                ->nullable()
                ->after('external_bank_account_id')
                ->constrained('external_bank_import_batches')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('external_bank_imports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('external_bank_import_batch_id');
        });

        Schema::dropIfExists('external_bank_import_batches');
    }
};

