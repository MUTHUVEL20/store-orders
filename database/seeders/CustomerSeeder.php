<?php

namespace Database\Seeders;

use App\Models\Customer;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        $customers = [
            ['John Doe', 'john@example.com'],
            ['Priya Sharma', 'priya.sharma@example.com'],
            ['Arjun Mehta', 'arjun.mehta@example.com'],
            ['Fatima Khan', 'fatima.khan@example.com'],
            ['Ravi Kumar', 'ravi.kumar@example.com'],
        ];

        foreach ($customers as [$name, $email]) {
            Customer::firstOrCreate(['email' => $email], ['name' => $name]);
        }
    }
}
