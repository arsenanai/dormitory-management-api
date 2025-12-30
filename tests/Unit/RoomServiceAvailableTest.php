<?php

namespace Tests\Unit;

use App\Models\Bed;
use App\Models\Dormitory;
use App\Models\Room;
use App\Models\RoomType;
use App\Services\RoomService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoomServiceAvailableTest extends TestCase
{
    use RefreshDatabase;

    private RoomService $roomService;
    private Dormitory $dormitory;
    private RoomType $roomType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->roomService = new RoomService();

        // Create test data
        $this->dormitory = Dormitory::factory()->create([
            'name' => 'Test Dormitory',
            'capacity' => 100,
            'gender' => 'male',
        ]);

        $this->roomType = RoomType::factory()->create([
            'name' => 'Standard Room',
            'capacity' => 2,
            'daily_rate' => 150.00,
            'semester_rate' => 20000.00,
        ]);
    }

    /** @test */
    public function it_returns_only_rooms_with_available_beds_for_students()
    {
        // Create rooms with different bed availability scenarios
        $roomWithAvailableBeds = Room::factory()->create([
            'dormitory_id' => $this->dormitory->id,
            'room_type_id' => $this->roomType->id,
            'number' => '101',
            'occupant_type' => 'student',
        ]);

        $roomWithOccupiedBeds = Room::factory()->create([
            'dormitory_id' => $this->dormitory->id,
            'room_type_id' => $this->roomType->id,
            'number' => '102',
            'occupant_type' => 'student',
        ]);

        $roomWithReservedBeds = Room::factory()->create([
            'dormitory_id' => $this->dormitory->id,
            'room_type_id' => $this->roomType->id,
            'number' => '103',
            'occupant_type' => 'student',
        ]);

        $guestRoom = Room::factory()->create([
            'dormitory_id' => $this->dormitory->id,
            'room_type_id' => $this->roomType->id,
            'number' => '104',
            'occupant_type' => 'guest',
        ]);

        // Create beds for each room manually with unique bed numbers
        // Room 101: One available bed, one occupied
        Bed::create([
            'room_id' => $roomWithAvailableBeds->id,
            'bed_number' => 1,
            'is_occupied' => false,
            'reserved_for_staff' => false,
            'user_id' => null,
        ]);

        Bed::create([
            'room_id' => $roomWithAvailableBeds->id,
            'bed_number' => 2,
            'is_occupied' => true,
            'reserved_for_staff' => false,
            'user_id' => 101,
        ]);

        // Room 102: All beds occupied
        Bed::create([
            'room_id' => $roomWithOccupiedBeds->id,
            'bed_number' => 1,
            'is_occupied' => true,
            'reserved_for_staff' => false,
            'user_id' => 102,
        ]);

        Bed::create([
            'room_id' => $roomWithOccupiedBeds->id,
            'bed_number' => 2,
            'is_occupied' => true,
            'reserved_for_staff' => false,
            'user_id' => 103,
        ]);

        // Room 103: All beds reserved for staff
        Bed::create([
            'room_id' => $roomWithReservedBeds->id,
            'bed_number' => 1,
            'is_occupied' => false,
            'reserved_for_staff' => true,
            'user_id' => null,
        ]);

        Bed::create([
            'room_id' => $roomWithReservedBeds->id,
            'bed_number' => 2,
            'is_occupied' => false,
            'reserved_for_staff' => true,
            'user_id' => null,
        ]);

        // Guest room: Available beds but wrong occupant type
        Bed::create([
            'room_id' => $guestRoom->id,
            'bed_number' => 1,
            'is_occupied' => false,
            'reserved_for_staff' => false,
            'user_id' => null,
        ]);

        // Call the service method
        $availableRooms = $this->roomService->available($this->dormitory->id, 'student');

        // Assertions
        $this->assertCount(1, $availableRooms);
        $this->assertEquals('101', $availableRooms->first()->number);

        // Verify the beds are loaded correctly
        $room = $availableRooms->first();
        $this->assertCount(1, $room->beds);
        $this->assertFalse($room->beds->first()->is_occupied);
        $this->assertFalse($room->beds->first()->reserved_for_staff);
    }

    /** @test */
    public function it_returns_empty_collection_when_no_rooms_have_available_beds()
    {
        // Create a room with all beds occupied
        $room = Room::factory()->create([
            'dormitory_id' => $this->dormitory->id,
            'room_type_id' => $this->roomType->id,
            'number' => '201',
            'occupant_type' => 'student',
        ]);

        // Create occupied beds
        Bed::create([
            'room_id' => $room->id,
            'bed_number' => 1,
            'is_occupied' => true,
            'reserved_for_staff' => false,
            'user_id' => 201,
        ]);

        Bed::create([
            'room_id' => $room->id,
            'bed_number' => 2,
            'is_occupied' => true,
            'reserved_for_staff' => false,
            'user_id' => 202,
        ]);

        // Call the service method
        $availableRooms = $this->roomService->available($this->dormitory->id, 'student');

        // Assertions
        $this->assertCount(0, $availableRooms);
    }

    /** @test */
    public function it_filters_by_dormitory_id()
    {
        // Create another dormitory
        $otherDormitory = Dormitory::factory()->create([
            'name' => 'Other Dormitory',
            'capacity' => 50,
            'gender' => 'female',
        ]);

        // Create rooms in both dormitories
        $roomInTargetDorm = Room::factory()->create([
            'dormitory_id' => $this->dormitory->id,
            'room_type_id' => $this->roomType->id,
            'number' => '301',
            'occupant_type' => 'student',
        ]);

        $roomInOtherDorm = Room::factory()->create([
            'dormitory_id' => $otherDormitory->id,
            'room_type_id' => $this->roomType->id,
            'number' => '302',
            'occupant_type' => 'student',
        ]);

        // Create available beds in both rooms
        Bed::create([
            'room_id' => $roomInTargetDorm->id,
            'bed_number' => 1,
            'is_occupied' => false,
            'reserved_for_staff' => false,
            'user_id' => null,
        ]);

        Bed::create([
            'room_id' => $roomInOtherDorm->id,
            'bed_number' => 1,
            'is_occupied' => false,
            'reserved_for_staff' => false,
            'user_id' => null,
        ]);

        // Call the service method for target dormitory
        $availableRooms = $this->roomService->available($this->dormitory->id, 'student');

        // Assertions - should only return room from target dormitory
        $this->assertCount(1, $availableRooms);
        $this->assertEquals('301', $availableRooms->first()->number);
    }

    /** @test */
    public function it_returns_multiple_rooms_with_available_beds()
    {
        // Create multiple rooms with available beds
        $room1 = Room::factory()->create([
            'dormitory_id' => $this->dormitory->id,
            'room_type_id' => $this->roomType->id,
            'number' => '401',
            'occupant_type' => 'student',
        ]);

        $room2 = Room::factory()->create([
            'dormitory_id' => $this->dormitory->id,
            'room_type_id' => $this->roomType->id,
            'number' => '402',
            'occupant_type' => 'student',
        ]);

        // Create available beds in both rooms
        Bed::create([
            'room_id' => $room1->id,
            'bed_number' => 1,
            'is_occupied' => false,
            'reserved_for_staff' => false,
            'user_id' => null,
        ]);

        Bed::create([
            'room_id' => $room2->id,
            'bed_number' => 1,
            'is_occupied' => false,
            'reserved_for_staff' => false,
            'user_id' => null,
        ]);

        // Call the service method
        $availableRooms = $this->roomService->available($this->dormitory->id, 'student');

        // Assertions
        $this->assertCount(2, $availableRooms);
        $roomNumbers = $availableRooms->pluck('number')->sort()->values()->toArray();
        $this->assertEquals(['401', '402'], $roomNumbers);
    }

    /** @test */
    public function it_includes_room_type_and_dormitory_relationships()
    {
        // Create a room with available beds
        $room = Room::factory()->create([
            'dormitory_id' => $this->dormitory->id,
            'room_type_id' => $this->roomType->id,
            'number' => '501',
            'occupant_type' => 'student',
        ]);

        Bed::create([
            'room_id' => $room->id,
            'bed_number' => 1,
            'is_occupied' => false,
            'reserved_for_staff' => false,
            'user_id' => null,
        ]);

        // Call the service method
        $availableRooms = $this->roomService->available($this->dormitory->id, 'student');

        // Assertions
        $this->assertCount(1, $availableRooms);
        $returnedRoom = $availableRooms->first();

        // Check relationships are loaded
        $this->assertNotNull($returnedRoom->dormitory);
        $this->assertEquals($this->dormitory->id, $returnedRoom->dormitory->id);
        $this->assertEquals('Test Dormitory', $returnedRoom->dormitory->name);

        $this->assertNotNull($returnedRoom->roomType);
        $this->assertEquals($this->roomType->id, $returnedRoom->roomType->id);
        $this->assertEquals('Standard Room', $returnedRoom->roomType->name);
    }
}
