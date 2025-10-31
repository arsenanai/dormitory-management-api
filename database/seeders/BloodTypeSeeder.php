<?php

namespace Database\Seeders;

use App\Models\BloodType;
use Illuminate\Database\Seeder;

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
            BloodType::firstOrCreate(['name' => $bloodType]);
        }
    }
}
