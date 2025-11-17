<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\RoomType;

class RoomTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        RoomType::firstOrCreate(
            ['name' => 'standard'],
            [
                'capacity'      => 2,
                'daily_rate'    => 10000.00,
                'semester_rate' => 300000.00,
                'minimap'       => null,
                'beds'          => json_encode([
                    ['x' => 10, 'y' => 20, 'width' => 30, 'height' => 40],
                    ['x' => 50, 'y' => 60, 'width' => 30, 'height' => 40]
                ]),
                'photos'        => json_encode([]),
            ]
        );

        RoomType::firstOrCreate(
            ['name' => 'lux'],
            [
                'capacity'      => 1,
                'daily_rate'    => 20000.00,
                'semester_rate' => 500000.00,
                'minimap'       => null,
                'beds'          => json_encode([
                    ['x' => 10, 'y' => 20, 'width' => 30, 'height' => 40],
                ]),
                'photos'        => json_encode([]),
            ]
        );
    }
}