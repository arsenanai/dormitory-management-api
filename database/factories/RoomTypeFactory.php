<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RoomType>
 */
class RoomTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name'          => $this->faker->randomElement(['standard', 'lux']),
            'capacity'      => $this->faker->randomElement([1, 2, 3, 4]),
            'daily_rate'    => $this->faker->randomFloat(2, 100, 300),
            'semester_rate' => $this->faker->randomFloat(2, 20000, 50000),
            'minimap'       => null,
            'beds'          => json_encode([
               ['x' => 10, 'y' => 20, 'width' => 30, 'height' => 40],
           ]),
           'photos'   => json_encode([
               $this->faker->imageUrl(640, 480, 'room', true),
               $this->faker->imageUrl(640, 480, 'room', true),
           ]),
       ];
    }    

    /**
     * Indicate that the room type is standard.
     */
    public function standard(): static
    {
        return $this->state(fn (array $attributes) => [
            'name'          => 'standard',
            'capacity'      => 2,
            'daily_rate'    => 150.00,
            'semester_rate' => 20000.00,
        ]);
    }

    /**
     * Indicate that the room type is lux.
     */
    public function lux(): static
    {
        return $this->state(fn (array $attributes) => [
            'name'          => 'lux',
            'capacity'      => 1,
            'daily_rate'    => 300.00,
            'semester_rate' => 40000.00,
        ]);
    }
}
