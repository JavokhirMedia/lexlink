<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database. Model events stay ON (User PINFL hashing relies on them).
     */
    public function run(): void
    {
        $this->call([
            PlansSeeder::class,
            MockGovSeeder::class,
            DemoUsersSeeder::class,
            AdvocateOrdersSeeder::class,
        ]);
    }
}
