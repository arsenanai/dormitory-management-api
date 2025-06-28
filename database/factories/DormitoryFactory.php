<?php

namespace Database\Factories;

use App\Models\Dormitory;
use Illuminate\Database\Eloquent\Factories\Factory;

class DormitoryFactory extends Factory {
	protected $model = Dormitory::class;

	public function definition() {
		return [ 
			'name'     => $this->faker->company . ' Dorm',
			'capacity' => $this->faker->numberBetween( 50, 500 ),
			'admin_id' => null,
		];
	}
}