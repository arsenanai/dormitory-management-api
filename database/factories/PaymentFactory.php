<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'amount' => $this->faker->randomFloat(2, 10000, 150000),
            'date_from' => $this->faker->date(),
            'date_to' => $this->faker->date(),
            'deal_number' => $this->faker->unique()->numerify('DEAL-####'),
            'deal_date' => $this->faker->date(),
            'payment_check' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}