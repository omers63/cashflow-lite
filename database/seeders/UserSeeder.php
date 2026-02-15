<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Create admin user
        User::create([
            'user_code' => 'USER001',
            'name' => 'Admin User',
            'email' => 'admin@cashflow.com',
            'password' => Hash::make('password'),
            'status' => 'active',
            'bank_account_balance' => 0,
            'fund_account_balance' => 0,
            'outstanding_loans' => 0,
        ]);
    }
}
