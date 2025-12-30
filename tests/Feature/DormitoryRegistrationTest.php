<?php

namespace Tests\Feature;

use App\Models\Bed;
use App\Models\Dormitory;
use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DormitoryRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private Dormitory $dormitory;
    private RoomType $roomType;

    protected function setUp(): void
    {
        parent::setUp();

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
    public function it_returns_only_rooms_with_available_beds_for_registration()
    {
        // Create rooms with different availability scenarios
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

        $guestRoom = Room::factory()->create([
            'dormitory_id' => $this->dormitory->id,
            'room_type_id' => $this->roomType->id,
            'number' => '201',
            'occupant_type' => 'guest',
        ]);

        // Create beds for each room manually to avoid conflicts
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

        // Guest room: Available beds but wrong occupant type
        Bed::create([
            'room_id' => $guestRoom->id,
            'bed_number' => 1,
            'is_occupied' => false,
            'reserved_for_staff' => false,
            'user_id' => null,
        ]);

        // Call the registration endpoint
        $response = $this->getJson("/api/dormitories/{$this->dormitory->id}/registration");

        // Assertions
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'data' => [
                        'id',
                        'name',
                        'address',
                        'capacity',
                        'gender',
                        'rooms' => [
                            '*' => [
                                'id',
                                'number',
                                'floor',
                                'dormitory_id',
                                'room_type_id',
                                'occupant_type',
                                'roomType',
                                'beds'
                            ]
                        ]
                    ]
                ]);

        $data = $response->json('data');
        $this->assertCount(1, $data['rooms']);
        $this->assertEquals('101', $data['rooms'][0]['number']);

        // Verify beds are filtered correctly
        $beds = $data['rooms'][0]['beds'];
        $this->assertCount(1, $beds);
        $this->assertFalse($beds[0]['is_occupied']);
        $this->assertFalse($beds[0]['reserved_for_staff']);
    }

    /** @test */
    public function it_returns_empty_rooms_array_when_no_rooms_have_available_beds()
    {
        // Create a room with all beds occupied
        $room = Room::factory()->create([
            'dormitory_id' => $this->dormitory->id,
            'room_type_id' => $this->roomType->id,
            'number' => '101',
            'occupant_type' => 'student',
        ]);

        Bed::create([
            'room_id' => $room->id,
            'bed_number' => 1,
            'is_occupied' => true,
            'reserved_for_staff' => false,
            'user_id' => 1,
        ]);

        // Call the registration endpoint
        $response = $this->getJson("/api/dormitories/{$this->dormitory->id}/registration");

        // Assertions
        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(0, $data['rooms']);
    }

    /** @test */
    public function it_returns_404_for_non_existent_dormitory()
    {
        $nonExistentId = 999;

        $response = $this->getJson("/api/dormitories/{$nonExistentId}/registration");

        $response->assertStatus(404)
                ->assertJson([
                    'error' => 'Dormitory not found'
                ]);
    }

    /** @test */
    public function it_includes_dormitory_details_in_response()
    {
        // Call the registration endpoint (even with no available rooms)
        $response = $this->getJson("/api/dormitories/{$this->dormitory->id}/registration");

        // Assertions
        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertEquals($this->dormitory->id, $data['id']);
        $this->assertEquals('Test Dormitory', $data['name']);
        $this->assertEquals(100, $data['capacity']);
        $this->assertEquals('male', $data['gender']);
        $this->assertArrayHasKey('rooms', $data);
    }

    /** @test */
    public function it_filters_rooms_by_student_occupant_type()
    {
        // Create student and guest rooms with available beds
        $studentRoom = Room::factory()->create([
            'dormitory_id' => $this->dormitory->id,
            'room_type_id' => $this->roomType->id,
            'number' => '101',
            'occupant_type' => 'student',
        ]);

        $guestRoom = Room::factory()->create([
            'dormitory_id' => $this->dormitory->id,
            'room_type_id' => $this->roomType->id,
            'number' => '201',
            'occupant_type' => 'guest',
        ]);

        // Create available beds in both rooms
        Bed::create([
            'room_id' => $studentRoom->id,
            'bed_number' => 1,
            'is_occupied' => false,
            'reserved_for_staff' => false,
            'user_id' => null,
        ]);

        Bed::create([
            'room_id' => $guestRoom->id,
            'bed_number' => 1,
            'is_occupied' => false,
            'reserved_for_staff' => false,
            'user_id' => null,
        ]);

        // Call the registration endpoint
        $response = $this->getJson("/api/dormitories/{$this->dormitory->id}/registration");

        // Assertions - should only return student room
        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data['rooms']);
        $this->assertEquals('101', $data['rooms'][0]['number']);
        $this->assertEquals('student', $data['rooms'][0]['occupant_type']);
    }

    /** @test */
    public function it_excludes_rooms_with_beds_reserved_for_staff()
    {
        // Create a room with staff-reserved beds
        $room = Room::factory()->create([
            'dormitory_id' => $this->dormitory->id,
            'room_type_id' => $this->roomType->id,
            'number' => '101',
            'occupant_type' => 'student',
        ]);

        Bed::create([
            'room_id' => $room->id,
            'bed_number' => 1,
            'is_occupied' => false,
            'reserved_for_staff' => true,
            'user_id' => null,
        ]);

        // Call the registration endpoint
        $response = $this->getJson("/api/dormitories/{$this->dormitory->id}/registration");

        // Assertions - should return no rooms
        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(0, $data['rooms']);
    }

    /** @test */
    public function it_returns_multiple_rooms_with_partial_availability()
    {
        // Create multiple rooms with some available beds
        $room1 = Room::factory()->create([
            'dormitory_id' => $this->dormitory->id,
            'room_type_id' => $this->roomType->id,
            'number' => '101',
            'occupant_type' => 'student',
        ]);

        $room2 = Room::factory()->create([
            'dormitory_id' => $this->dormitory->id,
            'room_type_id' => $this->roomType->id,
            'number' => '102',
            'occupant_type' => 'student',
        ]);

        // Room 1: One available, one occupied
        Bed::create([
            'room_id' => $room1->id,
            'bed_number' => 1,
            'is_occupied' => false,
            'reserved_for_staff' => false,
            'user_id' => null,
        ]);

        Bed::create([
            'room_id' => $room1->id,
            'bed_number' => 2,
            'is_occupied' => true,
            'reserved_for_staff' => false,
            'user_id' => 1,
        ]);

        // Room 2: One available
        Bed::create([
            'room_id' => $room2->id,
            'bed_number' => 1,
            'is_occupied' => false,
            'reserved_for_staff' => false,
        ]);

        // Call the registration endpoint
        $response = $this->getJson("/api/dormitories/{$this->dormitory->id}/registration");

        // Assertions
        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(2, $data['rooms']);

        $roomNumbers = collect($data['rooms'])->pluck('number')->sort()->values()->toArray();
        $this->assertEquals(['101', '102'], $roomNumbers);

        // Verify beds are filtered correctly in each room
        foreach ($data['rooms'] as $room) {
            $beds = collect($room['beds']);
            $this->assertGreaterThan(0, $beds->count());

            foreach ($beds as $bed) {
                $this->assertFalse($bed['is_occupied']);
                $this->assertFalse($bed['reserved_for_staff']);
            }
        }
    }

    /** @test */
    public function it_includes_room_type_details_with_rooms()
    {
        // Create a room with available beds
        $room = Room::factory()->create([
            'dormitory_id' => $this->dormitory->id,
            'room_type_id' => $this->roomType->id,
            'number' => '101',
            'occupant_type' => 'student',
        ]);

        Bed::create([
            'room_id' => $room->id,
            'bed_number' => 1,
            'is_occupied' => false,
            'reserved_for_staff' => false,
            'user_id' => null,
        ]);

        // Call the registration endpoint
        $response = $this->getJson("/api/dormitories/{$this->dormitory->id}/registration");

        // Assertions
        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data['rooms']);

        $roomData = $data['rooms'][0];
        $this->assertArrayHasKey('roomType', $roomData);
        $this->assertEquals($this->roomType->id, $roomData['roomType']['id']);
        $this->assertEquals('Standard Room', $roomData['roomType']['name']);
        $this->assertEquals(2, $roomData['roomType']['capacity']);
    }

    /** @test */
    public function it_sets_cache_control_headers()
    {
        $response = $this->getJson("/api/dormitories/{$this->dormitory->id}/registration");

        $response->assertStatus(200)
                ->assertHeader('Cache-Control', 'no-cache, no-store, must-revalidate')
                ->assertHeader('Pragma', 'no-cache')
                ->assertHeader('Expires', '0');
    }
}
