<?php

namespace Database\Factories;

use App\Models\Dormitory;
use Illuminate\Database\Eloquent\Factories\Factory;

class DormitoryFactory extends Factory {
	protected $model = Dormitory::class;

	public function definition() {
	   return [
		   'name'        => $this->faker->company . ' Dorm',
		   'address'     => $this->faker->address,
		   'description' => $this->faker->sentence,
		   'gender'      => $this->faker->randomElement(['male', 'female', 'mixed']),
		   'capacity'    => $this->faker->numberBetween(50, 500),
		   'quota'       => $this->faker->numberBetween(50, 500),
		   'phone'       => $this->faker->phoneNumber,
		   'admin_id'    => null,
	   ];
	}
}