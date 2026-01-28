<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PaymentType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentType>
 */
class PaymentTypeFactory extends Factory
{
    protected $model = PaymentType::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name'               => $this->faker->unique()->word() . ' Fee',
            'frequency'          => $this->faker->randomElement([ 'monthly', 'semesterly', 'once' ]),
            'calculation_method' => $this->faker->randomElement([ 'room_semester_rate', 'room_daily_rate', 'fixed' ]),
            'fixed_amount'       => $this->faker->randomFloat(2, 10, 500),
            'target_role'        => $this->faker->randomElement([ 'student', 'guest' ]),
            'trigger_event'      => null,
        ];
    }
}
