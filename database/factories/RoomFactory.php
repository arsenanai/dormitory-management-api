<?php

namespace Database\Factories;

use App\Models\Bed;
use App\Models\Dormitory;
use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Room>
 */
class RoomFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
           'number'       => ($this->faker->randomElement(['A', 'B', 'C']) . $this->faker->numberBetween(100, 999)),
           'floor'        => $this->faker->numberBetween(1, 10),
           'notes'        => $this->faker->optional()->sentence(),
           'dormitory_id' => Dormitory::factory(),
           'room_type_id' => RoomType::factory(),
        ];
    }

    /**
     * Configure the model factory.
     *
     * @return static
     */
    public function configure(): self
    {
        return $this->afterCreating(function (Room $room) {
            // After creating a room, create beds for it based on the room type's capacity.
            $roomType = $room->roomType()->first();
            if ($roomType && isset($roomType->capacity)) {
                $capacity = $roomType->capacity;
                for ($i = 1; $i <= $capacity; $i++) {
                    Bed::factory()->create(['room_id' => $room->id, 'bed_number' => $i]);
                }
            }
        });
    }
}
