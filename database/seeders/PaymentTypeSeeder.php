<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PaymentTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $types = [
            'renting',
            'catering',
            'all-inclusive'
        ];

        foreach ($types as $type) {
            \App\Models\PaymentType::updateOrCreate(['name' => $type]);
        }
    }
}
