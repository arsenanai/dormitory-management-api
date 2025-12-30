<?php

namespace Database\Factories;

use App\Models\Dormitory;
use App\Models\Message;
use App\Models\Room;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Message>
 */
class MessageFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Message::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'sender_id'      => User::factory(),
            'title'          => $this->faker->sentence(4),
            'content'        => $this->faker->paragraph(3),
            'recipient_type' => $this->faker->randomElement(['all', 'dormitory', 'room', 'individual']),
            'dormitory_id'   => null,
            'room_id'        => null,
            'recipient_ids'  => json_encode([]),
            'status'         => $this->faker->randomElement([ 'draft', 'sent' ]),
            'sent_at'        => function (array $attributes) {
                return $attributes['status'] === 'sent' ? $this->faker->dateTimeBetween('-1 month', 'now') : null;
            },
        ];
    }

    /**
     * Indicate that the message is a draft.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'  => 'draft',
            'sent_at' => null,
        ]);
    }

    /**
     * Indicate that the message is sent.
     */
    public function sent(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'  => 'sent',
            'sent_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
        ]);
    }

    /**
     * Indicate that the message is for all recipients.
     */
    public function forAll(): static
    {
        return $this->state(fn (array $attributes) => [
            'recipient_type' => 'all',
            'dormitory_id'   => null,
            'room_id'        => null,
            'recipient_ids'  => null,
        ]);
    }

    /**
     * Indicate that the message is for a specific dormitory.
     */
    public function forDormitory(?Dormitory $dormitory = null): static
    {
        return $this->state(fn (array $attributes) => [
            'recipient_type' => 'dormitory',
            'dormitory_id'   => $dormitory ? $dormitory->id : Dormitory::factory(),
            'room_id'        => null,
            'recipient_ids'  => null,
        ]);
    }

    /**
     * Indicate that the message is for a specific room.
     */
    public function forRoom(?Room $room = null): static
    {
        return $this->state(fn (array $attributes) => [
            'recipient_type' => 'room',
            'dormitory_id'   => null,
            'room_id'        => $room ? $room->id : Room::factory(),
            'recipient_ids'  => null,
        ]);
    }

    /**
     * Indicate that the message is for specific individuals.
     */
    public function forIndividuals(array $userIds = []): static
    {
        if (empty($userIds)) {
            $userIds = [ User::factory()->create()->id ];
        }

        return $this->state(fn (array $attributes) => [
            'recipient_type' => 'individual',
            'dormitory_id'   => null,
            'room_id'        => null,
            'recipient_ids'  => json_encode($userIds),
        ]);
    }
}
