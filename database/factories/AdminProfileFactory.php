<?php

namespace Database\Factories;

use App\Models\AdminProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AdminProfileFactory extends Factory {
	protected $model = AdminProfile::class;

	public function definition() {
		return [ 
			'user_id'         => User::factory(),
			'position'        => $this->faker->jobTitle,
			'department'      => $this->faker->word,
			'office_phone'    => $this->faker->phoneNumber,
			'office_location' => $this->faker->address,
		];
	}
}