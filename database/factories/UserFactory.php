<?php

namespace Database\Factories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $studentRole = Role::firstOrCreate(
            [ 'name' => 'student' ]
        );
        return [
			'iin'                      => $this->faker->unique()->numerify( '#########' ),
			'name'                     => $this->faker->name,
			'faculty'                  => $this->faker->randomElement( [ 'Engineering', 'Business', 'Law', 'Medicine' ] ),
			'specialist'               => $this->faker->jobTitle,
			'enrollment_year'          => $this->faker->year,
			'gender'                   => $this->faker->randomElement( [ 'male', 'female' ] ),
			'email'                    => $this->faker->unique()->safeEmail,
			'phone_numbers'            => json_encode( [ $this->faker->phoneNumber ] ),
			'room_id'                  => null,
			'password'                 => static::$password ??= Hash::make( 'password' ),
			'deal_number'              => $this->faker->numerify( '######' ),
			'city_id'                  => null,
			'files'                    => json_encode( [ null, null, null, null ] ),
			'agree_to_dormitory_rules' => $this->faker->boolean,
			'status'                   => $this->faker->randomElement( [ 'pending', 'active', 'passive' ] ),
			'role_id'                  => $studentRole->id,
			'email_verified_at'        => now(),
			'remember_token'           => Str::random( 10 ),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
