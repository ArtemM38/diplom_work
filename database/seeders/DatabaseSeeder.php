<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            SportsLookupSeeder::class,
            PortfolioLookupSeeder::class,
            LocationSeeder::class,
            AdminSeeder::class,
            DemoDataSeeder::class,
        ]);
    }
}
