<?php

namespace Database\Factories;

use App\Models\GuestProfile;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class GuestFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'name'              => $this->faker->name,
            'first_name'        => $this->faker->firstName,
            'last_name'         => $this->faker->lastName,
            'email'             => $this->faker->unique()->safeEmail,
            'email_verified_at' => now(),
            'phone_numbers'     => json_encode([ $this->faker->phoneNumber ]),
            'room_id'           => null,
            'password'          => Hash::make('password'),
            'status'            => 'pending',
            'role_id'           => Role::where('name', 'guest')->first()->id ?? 4,
            'remember_token'    => Str::random(10),
        ];
    }

    public function configure()
    {
        return $this->afterCreating(function (\Illuminate\Database\Eloquent\Model $model) {
            /** @var User $user */
            $user = $model;
            GuestProfile::factory()->create([ 'user_id' => $user->id ]);
        });
    }
}
