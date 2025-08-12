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
           'name'     => $this->faker->randomElement(['standard', 'lux']),
           'capacity' => $this->faker->randomElement([1, 2, 3, 4]),
           'price'    => $this->faker->randomElement(['standard' => 150.00, 'lux' => 300.00]),
           'minimap'  => null,
           'beds'     => json_encode([
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
            'name'     => 'standard',
            'capacity' => 2,
            'price'    => 150.00,
        ]);
    }

    /**
     * Indicate that the room type is lux.
     */
    public function lux(): static
    {
        return $this->state(fn (array $attributes) => [
            'name'     => 'lux',
            'capacity' => 1,
            'price'    => 300.00,
        ]);
    }
}
