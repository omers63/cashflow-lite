<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $types = [
        'external_import',
        'master_to_user_bank',
        'contribution',
        'loan_repayment',
        'loan_disbursement',
        'adjustment',
        'allocation_to_dependant',
        'allocation_from_parent',
        'import_deposit',
    ];

    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $this->upSqlite();
            return;
        }

        // MySQL / PostgreSQL
        $typeList = "'" . implode("','", $this->types) . "'";
        DB::statement("ALTER TABLE transactions MODIFY COLUMN type ENUM({$typeList}) NOT NULL");
    }

    public function down(): void
    {
        // Removing a type from an enum is risky; no-op.
    }

    private function upSqlite(): void
    {
        $typeList = "'" . implode("','", $this->types) . "'";

        DB::statement('PRAGMA foreign_keys=OFF');
        DB::statement('BEGIN TRANSACTION');

        DB::statement("
            CREATE TABLE transactions_new (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                transaction_id VARCHAR(255) NOT NULL UNIQUE,
                transaction_date DATETIME NOT NULL,
                \"type\" TEXT NOT NULL CHECK (\"type\" IN ({$typeList})),
                from_account VARCHAR(255) NOT NULL,
                to_account VARCHAR(255) NOT NULL,
                amount DECIMAL(15,2) NOT NULL,
                user_id INTEGER NULL,
                reference VARCHAR(255) NULL,
                status VARCHAR(255) NOT NULL DEFAULT 'complete' CHECK (status IN ('pending','complete','failed','reversed')),
                notes TEXT NULL,
                created_by INTEGER NULL,
                approved_by INTEGER NULL,
                approved_at DATETIME NULL,
                allocation_pair_id INTEGER NULL,
                updated_at DATETIME NULL,
                created_at DATETIME NULL,
                deleted_at DATETIME NULL,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
                FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
                FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
            )
        ");

        DB::statement("
            INSERT INTO transactions_new (
                id, transaction_id, transaction_date, \"type\", from_account, to_account, amount,
                user_id, reference, status, notes, created_by, approved_by, approved_at,
                allocation_pair_id, updated_at, created_at, deleted_at
            )
            SELECT id, transaction_id, transaction_date, type, from_account, to_account, amount,
                user_id, reference, status, notes, created_by, approved_by, approved_at,
                allocation_pair_id, updated_at, created_at, deleted_at
            FROM transactions
        ");

        DB::statement('DROP TABLE transactions');
        DB::statement('ALTER TABLE transactions_new RENAME TO transactions');

        DB::statement('CREATE UNIQUE INDEX transactions_transaction_id_unique ON transactions (transaction_id)');
        DB::statement('CREATE INDEX transactions_transaction_date_index ON transactions (transaction_date)');
        DB::statement('CREATE INDEX transactions_type_index ON transactions (type)');
        DB::statement('CREATE INDEX transactions_status_index ON transactions (status)');
        DB::statement('CREATE INDEX transactions_user_id_type_index ON transactions (user_id, type)');
        DB::statement('CREATE INDEX transactions_transaction_date_type_index ON transactions (transaction_date, type)');
        DB::statement('CREATE INDEX transactions_reference_index ON transactions (reference)');
        DB::statement('CREATE INDEX transactions_allocation_pair_id_index ON transactions (allocation_pair_id)');

        DB::statement('COMMIT');
        DB::statement('PRAGMA foreign_keys=ON');
    }
};
