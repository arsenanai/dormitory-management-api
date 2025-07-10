<?php

namespace Database\Factories;

use App\Models\Bed;
use App\Models\Room;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Bed>
 */
class BedFactory extends Factory {
	/**
	 * The name of the factory's corresponding model.
	 */
	protected $model = Bed::class;

	/**
	 * Define the model's default state.
	 */
	public function definition(): array {
	   return [
		   'room_id'    => Room::factory(),
		   'bed_number' => $this->faker->numberBetween(1, 4),
		   'user_id'    => null,
	   ];
	}

	/**
	 * Indicate that the bed is occupied.
	 */
	public function occupied( ?User $user = null ): static {
		return $this->state( fn( array $attributes ) => [ 
			'user_id' => $user?->id ?? User::factory(),
		] );
	}

	/**
	 * Indicate that the bed is available.
	 */
	public function available(): static {
		return $this->state( fn( array $attributes ) => [ 
			'user_id' => null,
		] );
	}
}
