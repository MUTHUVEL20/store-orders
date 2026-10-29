<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Demo data (and ProductSeeder's stock reset) must never touch a live database.
        if (app()->isProduction()) {
            $this->command?->warn('Demo data is not seeded in production.');

            return;
        }

        $this->call([
            ProductSeeder::class,
            CustomerSeeder::class,
            OrderSeeder::class,
        ]);
    }
}
