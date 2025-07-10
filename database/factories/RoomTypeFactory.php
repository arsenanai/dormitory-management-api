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
           'name'    => $this->faker->randomElement(['standard', 'lux']),
           'minimap' => null,
           'beds'    => json_encode([
               ['x' => 10, 'y' => 20, 'width' => 30, 'height' => 40],
           ]),
           'photos'  => json_encode([
               $this->faker->imageUrl(640, 480, 'room', true),
               $this->faker->imageUrl(640, 480, 'room', true),
           ]),
       ];
    }
}
