<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BloodTypeSeeder extends Seeder
{
    public function run(): void
    {
        $bloodTypes = [
            'A+',
            'A-',
            'B+',
            'B-',
            'AB+',
            'AB-',
            'O+',
            'O-'
        ];

        foreach ($bloodTypes as $bloodType) {
            DB::table('blood_types')->insertOrIgnore([
                'name' => $bloodType,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
    }
}
