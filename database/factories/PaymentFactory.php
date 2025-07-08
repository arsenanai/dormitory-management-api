<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Payment>
 */
class PaymentFactory extends Factory {
	/**
	 * The name of the factory's corresponding model.
	 */
	protected $model = Payment::class;

	/**
	 * Define the model's default state.
	 */
	public function definition(): array {
		return [ 
			'user_id'         => User::factory(),
			'transaction_id'  => 'TXN-' . $this->faker->unique()->numberBetween( 100000, 999999 ),
			'name'            => $this->faker->firstName(),
			'surname'         => $this->faker->lastName(),
			'contract_number' => 'CONTRACT-' . $this->faker->year() . '-' . $this->faker->numberBetween( 1, 999 ),
			'amount'          => $this->faker->numberBetween( 30000, 100000 ),
			'contract_date'   => $this->faker->dateTimeBetween( '-1 year', 'now' ),
			'payment_date'    => $this->faker->dateTimeBetween( '-6 months', 'now' ),
			'payment_method'  => $this->faker->randomElement( [ 'cash', 'card', 'bank_transfer', 'online' ] ),
			'receipt_file'    => 'payment_receipts/' . $this->faker->uuid() . '.pdf',
			'status'          => $this->faker->randomElement( [ 'pending', 'completed', 'failed', 'refunded' ] ),
			'description'     => $this->faker->optional()->sentence(),
		];
	}

	/**
	 * Indicate that the payment is completed.
	 */
	public function completed(): static {
		return $this->state( fn( array $attributes ) => [ 
			'status'       => 'completed',
			'payment_date' => $this->faker->dateTimeBetween( '-3 months', 'now' ),
		] );
	}

	/**
	 * Indicate that the payment is pending.
	 */
	public function pending(): static {
		return $this->state( fn( array $attributes ) => [ 
			'status'       => 'pending',
			'payment_date' => null,
		] );
	}

	/**
	 * Indicate that the payment is failed.
	 */
	public function failed(): static {
		return $this->state( fn( array $attributes ) => [ 
			'status'       => 'failed',
			'payment_date' => $this->faker->dateTimeBetween( '-1 month', 'now' ),
		] );
	}
}
