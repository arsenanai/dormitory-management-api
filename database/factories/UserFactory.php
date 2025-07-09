<?php

namespace Database\Factories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory {
	/**
	 * The current password being used by the factory.
	 */
	protected static ?string $password;

	/**
	 * Define the model's default state.
	 *
	 * @return array<string, mixed>
	 */
	public function definition(): array {
		return [ 
			'name'          => $this->faker->name,
			'first_name'    => $this->faker->firstName,
			'last_name'     => $this->faker->lastName,
			'email'         => $this->faker->unique()->safeEmail,
			'phone_numbers' => json_encode( [ $this->faker->phoneNumber ] ),
			'room_id'       => null,
			'password'      => static::$password ??= Hash::make( 'password' ),
			'status'        => 'pending',
			'role_id'       => 1, // default role, adjust as needed
		];
	}

	/**
	 * Indicate that the model's email address should be unverified.
	 */
	public function unverified(): static {
		return $this->state( fn( array $attributes ) => [ 
			'email_verified_at' => null,
		] );
	}
}
