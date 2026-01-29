<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\PaymentType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id'         => User::factory(),
            'payment_type_id' => PaymentType::factory(),
            'amount'          => $this->faker->randomFloat(2, 100, 5000),
            'date_from'       => $this->faker->date(),
            'date_to'         => $this->faker->date(),
            'deal_number'     => $this->faker->unique()->numerify('DEAL-####'),
            'deal_date'       => $this->faker->date(),
            'payment_check'   => null,
            'status'          => PaymentStatus::Pending,
        ];
    }
}
