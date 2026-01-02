<?php

namespace Tests\Feature;

use App\Models\Bed;
use App\Models\Dormitory;
use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class DormitoryRegistrationTest extends TestCase
{
    use DatabaseTransactions;

    /** @test */
    public function it_returns_only_rooms_with_available_beds_for_registration()
    {
        // Create fresh data with unique identifiers to prevent any conflicts
        $dormitory = Dormitory::factory()->create([
            'name' => 'Test Dormitory ' . uniqid(),
            'capacity' => 100,
            'gender' => 'male',
        ]);

        $roomType = RoomType::factory()->create([
            'name' => 'Standard Room ' . uniqid(),
            'capacity' => 2,
            'daily_rate' => 150.00,
            'semester_rate' => 20000.00,
        ]);

        // Create rooms with different availability scenarios
        $roomWithAvailableBeds = Room::factory()->create([
            'dormitory_id' => $dormitory->id,
            'room_type_id' => $roomType->id,
            'number' => '101',
            'occupant_type' => 'student',
        ]);

        $guestRoom = Room::factory()->create([
            'dormitory_id' => $dormitory->id,
            'room_type_id' => $roomType->id,
            'number' => '201',
            'occupant_type' => 'guest',
        ]);

        // Create beds with unique identifiers to avoid conflicts
        // Room 101: One available bed, one occupied
        Bed::create([
            'room_id' => $roomWithAvailableBeds->id,
            'bed_number' => ($roomWithAvailableBeds->id * 10) + 1, // Unique bed number
            'is_occupied' => false,
            'reserved_for_staff' => false,
            'user_id' => null,
        ]);

        Bed::create([
            'room_id' => $roomWithAvailableBeds->id,
            'bed_number' => ($roomWithAvailableBeds->id * 10) + 2, // Unique bed number
            'is_occupied' => true,
            'reserved_for_staff' => false,
            'user_id' => 101,
        ]);

        // Guest room: Available beds but wrong occupant type
        Bed::create([
            'room_id' => $guestRoom->id,
            'bed_number' => ($guestRoom->id * 10) + 1, // Unique bed number
            'is_occupied' => false,
            'reserved_for_staff' => false,
            'user_id' => null,
        ]);

        // Call the registration endpoint
        $response = $this->getJson("/api/dormitories/{$dormitory->id}/registration");

        // Assertions
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'data' => [
                        'id',
                        'name',
                        'rooms' => [
                            '*' => [
                                'id',
                                'number',
                                'beds'
                            ]
                        ]
                    ]
                ]);

        $data = $response->json('data');

        // Should only return rooms with available beds and correct occupant type
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
        // Create fresh data with unique identifiers
        $dormitory = Dormitory::factory()->create([
            'name' => 'Test Dormitory ' . uniqid(),
            'capacity' => 100,
            'gender' => 'male',
        ]);

        $roomType = RoomType::factory()->create([
            'name' => 'Standard Room ' . uniqid(),
            'capacity' => 2,
            'daily_rate' => 150.00,
            'semester_rate' => 20000.00,
        ]);

        // Create a room with all beds occupied
        $room = Room::factory()->create([
            'dormitory_id' => $dormitory->id,
            'room_type_id' => $roomType->id,
            'number' => '201',
            'occupant_type' => 'student',
        ]);

        Bed::create([
            'room_id' => $room->id,
            'bed_number' => ($room->id * 10) + 1, // Unique bed number
            'is_occupied' => true,
            'reserved_for_staff' => false,
            'user_id' => 1,
        ]);

        Bed::create([
            'room_id' => $room->id,
            'bed_number' => ($room->id * 10) + 2, // Unique bed number
            'is_occupied' => true,
            'reserved_for_staff' => false,
            'user_id' => 2,
        ]);

        // Call the registration endpoint
        $response = $this->getJson("/api/dormitories/{$dormitory->id}/registration");

        // Assertions
        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(0, $data['rooms']);
    }

    /** @test */
    public function it_includes_dormitory_details_in_response()
    {
        // Create fresh data
        $dormitory = Dormitory::factory()->create([
            'name' => 'Test Dormitory ' . uniqid(),
            'capacity' => 100,
            'gender' => 'male',
        ]);

        // Call the registration endpoint (even with no available rooms)
        $response = $this->getJson("/api/dormitories/{$dormitory->id}/registration");

        // Assertions
        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertEquals($dormitory->id, $data['id']);
        $this->assertStringContainsString('Test Dormitory', $data['name']);
        $this->assertEquals(100, $data['capacity']);
        $this->assertEquals('male', $data['gender']);
        $this->assertArrayHasKey('rooms', $data);
    }

    /** @test */
    public function it_sets_cache_control_headers()
    {
        // Create fresh data
        $dormitory = Dormitory::factory()->create([
            'name' => 'Test Dormitory ' . uniqid(),
            'capacity' => 100,
            'gender' => 'male',
        ]);

        $response = $this->getJson("/api/dormitories/{$dormitory->id}/registration");

        $response->assertStatus(200)
                ->assertHeader('Cache-Control', 'must-revalidate, no-cache, no-store, private')
                ->assertHeader('Pragma', 'no-cache')
                ->assertHeader('Expires', '0');
    }
}
