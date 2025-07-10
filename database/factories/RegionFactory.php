<?php

namespace Database\Factories;

use App\Models\Country;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Region>
 */
class RegionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
           'name'       => $this->faker->state,
           'language'   => $this->faker->randomElement(['en', 'ru', 'kk']),
           'country_id' => \App\Models\Country::factory(),
        ];
    }
}
