<?php

namespace Database\Seeders;

use App\Models\PaymentType;
use Illuminate\Database\Seeder;

class PaymentTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            [
                'name' => 'renting',
                'frequency' => 'semesterly',
                'calculation_method' => 'room_semester_rate',
                'target_role' => 'student',
                'trigger_event' => null, // Handles both registration and new_semester
            ],
            [
                'name' => 'catering',
                'frequency' => 'monthly',
                'calculation_method' => 'fixed',
                'fixed_amount' => 150.00,
                'target_role' => 'student',
                'trigger_event' => null, // Handles both registration and new_month
            ],
            [
                'name' => 'guest_stay',
                'frequency' => 'once',
                'calculation_method' => 'room_daily_rate',
                'target_role' => 'guest',
                'trigger_event' => 'registration',
            ],
        ];

        foreach ($types as $type) {
            PaymentType::updateOrCreate(['name' => $type['name']], $type);
        }
    }
}
