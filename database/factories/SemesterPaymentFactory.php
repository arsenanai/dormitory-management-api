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
		
		// Use microtime + random string to ensure uniqueness across all factory calls
		$uniqueId = substr(microtime(true) * 10000, -8) . '-' . fake()->unique()->lexify('???');

	   return [
		   'user_id'                   => User::factory(),
		   'semester'                  => $year . '-' . $semesterType . '-' . $uniqueId, // Make unique
		   'year'                      => $year,
		   'semester_type'             => $semesterType,
		   'amount'                    => fake()->randomFloat(2, 500, 5000),
		   'payment_approved'          => fake()->boolean(),
		   'dormitory_access_approved' => fake()->boolean(),
		   'payment_approved_at'       => null,
		   'dormitory_approved_at'     => null,
		   'payment_approved_by'       => null,
		   'dormitory_approved_by'     => null,
		   'due_date'                  => fake()->dateTimeBetween('now', '+3 months')->format('Y-m-d'),
		   'paid_date'                 => null,
		   'payment_notes'             => null,
		   'dormitory_notes'           => null,
		   'payment_status'            => 'pending',
		   'dormitory_status'          => 'pending',
		   'receipt_file'              => null,
		   'contract_number'           => 'CONTRACT-' . fake()->year() . '-' . fake()->unique()->numberBetween(1, 999),
		   'contract_date'             => fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
		   'payment_date'              => fake()->dateTimeBetween('-6 months', 'now')->format('Y-m-d'),
		   'payment_method'            => fake()->randomElement(['credit_card', 'bank_transfer', 'cash', 'check']),
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
