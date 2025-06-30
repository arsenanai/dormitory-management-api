<?php

namespace Database\Factories;

use App\Models\Dormitory;
use App\Models\RoomType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Room>
 */
class RoomFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [ 
			'number'       => $this->faker->randomElement( [ 'A', 'B', 'C' ] ) . $this->faker->numberBetween( 100, 999 ),
			'floor'        => $this->faker->numberBetween( 1, 10 ),
			'notes'        => $this->faker->optional()->sentence(),
			'dormitory_id' => Dormitory::factory(),
			'room_type_id' => RoomType::factory(),
        ];
    }
}
