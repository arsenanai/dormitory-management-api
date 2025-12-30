<?php

namespace Database\Factories;

use App\Models\AdminProfile;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminFactory extends Factory
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
            'status'            => 'active',
            'role_id'           => Role::where('name', 'admin')->first()->id ?? 2,
            'remember_token'    => Str::random(10),
        ];
    }

    public function configure()
    {
        return $this->afterCreating(function (\Illuminate\Database\Eloquent\Model $model) {
            /** @var User $user */
            $user = $model;
            AdminProfile::create([
                'user_id'         => $user->id,
                'position'        => $this->faker->jobTitle,
                'department'      => $this->faker->word,
                'office_phone'    => $this->faker->phoneNumber,
                'office_location' => $this->faker->address,
            ]);
        });
    }
}
