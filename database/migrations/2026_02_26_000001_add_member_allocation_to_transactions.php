<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $this->upSqlite();
        } else {
            Schema::table('transactions', function (Blueprint $table) {
                $table->unsignedBigInteger('allocation_pair_id')->nullable()->after('approved_at');
                $table->index('allocation_pair_id');
            });
            if ($driver === 'mysql') {
                DB::statement("ALTER TABLE transactions MODIFY COLUMN type ENUM(
                    'external_import',
                    'master_to_user_bank',
                    'contribution',
                    'loan_repayment',
                    'loan_disbursement',
                    'adjustment',
                    'allocation_to_dependant',
                    'allocation_from_parent'
                ) NOT NULL");
            }
        }
    }

    private function upSqlite(): void
    {
        $types = [
            'external_import',
            'master_to_user_bank',
            'contribution',
            'loan_repayment',
            'loan_disbursement',
            'adjustment',
            'allocation_to_dependant',
            'allocation_from_parent',
        ];
        $typeList = "'" . implode("','", $types) . "'";

        DB::statement('PRAGMA foreign_keys=OFF');
        DB::statement('BEGIN TRANSACTION');

        DB::statement("
            CREATE TABLE transactions_new (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                transaction_id VARCHAR(255) NOT NULL,
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
        DB::statement('
            INSERT INTO transactions_new (
                id, transaction_id, transaction_date, "type", from_account, to_account, amount,
                user_id, reference, status, notes, created_by, approved_by, approved_at,
                updated_at, created_at, deleted_at
            )
            SELECT id, transaction_id, transaction_date, type, from_account, to_account, amount,
                user_id, reference, status, notes, created_by, approved_by, approved_at,
                updated_at, created_at, deleted_at
            FROM transactions
        ');
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

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $this->downSqlite();
        } else {
            Schema::table('transactions', function (Blueprint $table) {
                $table->dropIndex(['allocation_pair_id']);
                $table->dropColumn('allocation_pair_id');
            });
            if ($driver === 'mysql') {
                DB::statement("ALTER TABLE transactions MODIFY COLUMN type ENUM(
                    'external_import',
                    'master_to_user_bank',
                    'contribution',
                    'loan_repayment',
                    'loan_disbursement',
                    'adjustment'
                ) NOT NULL");
            }
        }
    }

    private function downSqlite(): void
    {
        DB::statement('PRAGMA foreign_keys=OFF');
        DB::statement('BEGIN TRANSACTION');

        DB::statement("
            CREATE TABLE transactions_old (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                transaction_id VARCHAR(255) NOT NULL,
                transaction_date DATETIME NOT NULL,
                \"type\" CHECK (\"type\" IN ('external_import','master_to_user_bank','contribution','loan_repayment','loan_disbursement','adjustment')) NOT NULL,
                from_account VARCHAR(255) NOT NULL,
                to_account VARCHAR(255) NOT NULL,
                amount DECIMAL(15,2) NOT NULL,
                user_id INTEGER NULL,
                reference VARCHAR(255) NULL,
                status VARCHAR(255) NOT NULL DEFAULT 'complete',
                notes TEXT NULL,
                created_by INTEGER NULL,
                approved_by INTEGER NULL,
                approved_at DATETIME NULL,
                updated_at DATETIME NULL,
                created_at DATETIME NULL,
                deleted_at DATETIME NULL,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
                FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
                FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
            )
        ");
        DB::statement('
            INSERT INTO transactions_old (id, transaction_id, transaction_date, type, from_account, to_account, amount,
                user_id, reference, status, notes, created_by, approved_by, approved_at, updated_at, created_at, deleted_at)
            SELECT id, transaction_id, transaction_date, type, from_account, to_account, amount,
                user_id, reference, status, notes, created_by, approved_by, approved_at, updated_at, created_at, deleted_at
            FROM transactions
            WHERE type NOT IN (\'allocation_to_dependant\', \'allocation_from_parent\')
        ');
        DB::statement('DROP TABLE transactions');
        DB::statement('ALTER TABLE transactions_old RENAME TO transactions');
        DB::statement('CREATE UNIQUE INDEX transactions_transaction_id_unique ON transactions (transaction_id)');
        DB::statement('CREATE INDEX transactions_transaction_date_index ON transactions (transaction_date)');
        DB::statement('CREATE INDEX transactions_type_index ON transactions (type)');
        DB::statement('CREATE INDEX transactions_status_index ON transactions (status)');
        DB::statement('CREATE INDEX transactions_user_id_type_index ON transactions (user_id, type)');
        DB::statement('CREATE INDEX transactions_transaction_date_type_index ON transactions (transaction_date, type)');
        DB::statement('CREATE INDEX transactions_reference_index ON transactions (reference)');

        DB::statement('COMMIT');
        DB::statement('PRAGMA foreign_keys=ON');
    }
};
