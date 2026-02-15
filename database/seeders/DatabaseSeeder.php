<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            MasterAccountSeeder::class,
            UserSeeder::class,
            // DemoDataSeeder::class, // Uncomment for demo data
        ]);
    }
}
