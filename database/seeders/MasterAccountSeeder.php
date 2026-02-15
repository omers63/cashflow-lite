<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\MasterAccount;

class MasterAccountSeeder extends Seeder
{
    public function run(): void
    {
        // Master accounts are created in migration
        // This seeder can set initial balances if needed
        
        MasterAccount::where('account_type', 'master_bank')->first()?->update([
            'balance' => 0,
            'opening_balance' => 0,
        ]);

        MasterAccount::where('account_type', 'master_fund')->first()?->update([
            'balance' => 0,
            'opening_balance' => 0,
        ]);
    }
}
