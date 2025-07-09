<?php

namespace Database\Factories;

use App\Models\SemesterPayment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SemesterPayment>
 */
class SemesterPaymentFactory extends Factory {
	protected $model = SemesterPayment::class;

	/**
	 * Define the model's default state.
	 *
	 * @return array<string, mixed>
	 */
	public function definition(): array {
		$year = fake()->numberBetween( 2024, 2026 );
		$semesterType = fake()->randomElement( [ 'fall', 'spring', 'summer' ] );

		return [ 
			'user_id'                   => User::factory(),
			'semester'                  => $year . '-' . $semesterType,
			'year'                      => $year,
			'semester_type'             => $semesterType,
			'amount'                    => fake()->randomFloat( 2, 500, 5000 ),
			'payment_approved'          => fake()->boolean(),
			'dormitory_access_approved' => fake()->boolean(),
			'payment_approved_at'       => fake()->optional()->dateTimeBetween( '-1 month', 'now' ),
			'dormitory_approved_at'     => fake()->optional()->dateTimeBetween( '-1 month', 'now' ),
			'payment_approved_by'       => fake()->optional()->randomElement( [ User::factory(), null ] ),
			'dormitory_approved_by'     => fake()->optional()->randomElement( [ User::factory(), null ] ),
			'due_date'                  => fake()->dateTimeBetween( 'now', '+3 months' ),
			'paid_date'                 => fake()->optional()->dateTimeBetween( '-1 month', 'now' ),
			'payment_notes'             => fake()->optional()->sentence(),
			'dormitory_notes'           => fake()->optional()->sentence(),
			'payment_status'            => fake()->randomElement( [ 'pending', 'approved', 'rejected', 'expired' ] ),
			'dormitory_status'          => fake()->randomElement( [ 'pending', 'approved', 'rejected', 'expired' ] ),
		];
	}

	public function approved(): static {
		return $this->state( [ 
			'payment_approved'          => true,
			'dormitory_access_approved' => true,
			'payment_approved_at'       => now(),
			'dormitory_approved_at'     => now(),
			'payment_status'            => 'approved',
			'dormitory_status'          => 'approved',
		] );
	}

	public function currentSemester(): static {
		return $this->state( [ 
			'semester' => SemesterPayment::getCurrentSemester(),
		] );
	}
}
