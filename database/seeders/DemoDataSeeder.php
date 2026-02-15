<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\ExternalBankAccount;
use App\Models\Transaction;
use App\Models\Loan;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        // Create demo users
        User::factory(10)->create();

        // Create external bank accounts
        ExternalBankAccount::create([
            'bank_name' => 'Chase Bank',
            'account_number' => '****1234',
            'account_type' => 'checking',
            'current_balance' => 50000,
            'status' => 'active',
        ]);

        ExternalBankAccount::create([
            'bank_name' => 'Wells Fargo',
            'account_number' => '****5678',
            'account_type' => 'savings',
            'current_balance' => 25000,
            'status' => 'active',
        ]);

        // Create demo transactions
        Transaction::factory(50)->create();

        // Create demo loans
        Loan::factory(5)->create();
    }
}
