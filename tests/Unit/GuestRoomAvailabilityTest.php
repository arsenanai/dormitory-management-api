<?php

namespace Tests\Unit;

use App\Models\Bed;
use App\Models\Dormitory;
use App\Models\Room;
use App\Models\RoomType;
use App\Services\RoomService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuestRoomAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private RoomService $roomService;
    private Dormitory $dormitory;
    private RoomType $roomType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->roomService = new RoomService();

        $this->dormitory = Dormitory::factory()->create();
        $this->roomType = RoomType::factory()->create();
    }

    /** @test */
    public function it_returns_guest_rooms_for_guests()
    {
        $room = Room::factory()->create([
            'dormitory_id' => $this->dormitory->id,
            'room_type_id' => $this->roomType->id,
            'occupant_type' => 'guest',
        ]);

        Bed::create([
            'room_id' => $room->id,
            'bed_number' => 1,
        ]);

        $availableRooms = $this->roomService->available($this->dormitory->id, 'guest', [
            'start_date' => '2023-01-01',
            'end_date' => '2023-01-02',
        ]);

        $this->assertCount(1, $availableRooms);
    }

    /** @test */
    public function it_does_not_return_student_rooms_for_guests_by_default()
    {
        $room = Room::factory()->create([
            'dormitory_id' => $this->dormitory->id,
            'room_type_id' => $this->roomType->id,
            'occupant_type' => 'student',
        ]);

        Bed::create([
            'room_id' => $room->id,
            'bed_number' => 1,
        ]);

        $availableRooms = $this->roomService->available($this->dormitory->id, 'guest', [
            'start_date' => '2023-01-01',
            'end_date' => '2023-01-02',
        ]);

        $this->assertCount(0, $availableRooms);
    }
}
