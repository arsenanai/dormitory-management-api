<?php

namespace Database\Factories;

use App\Models\Role;
use App\Models\User;
use App\Models\StudentProfile;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class StudentFactory extends Factory {
	protected $model = User::class;

	public function definition(): array {
		return [ 
			'name'              => $this->faker->name,
			'first_name'        => $this->faker->firstName,
			'last_name'         => $this->faker->lastName,
			'email'             => $this->faker->unique()->safeEmail,
			'email_verified_at' => now(),
			'phone_numbers'     => json_encode( [ $this->faker->phoneNumber ] ),
			'room_id'           => null,
			'password'          => Hash::make( 'password' ),
			'status'            => 'pending',
			'role_id'           => Role::where( 'name', 'student' )->first()->id ?? 3,
			'remember_token'    => Str::random( 10 ),
			'iin'               => $this->faker->unique()->numerify( '##########' ) . rand( 10, 99 ),
		];
	}

	public function configure() {
		return $this->afterCreating( function (User $user) {
			StudentProfile::factory()->create( [ 'user_id' => $user->id ] );
		} );
	}
}