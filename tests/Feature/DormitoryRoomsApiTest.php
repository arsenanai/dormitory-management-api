<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Dormitory;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Bed;
use App\Models\AdminProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Foundation\Testing\WithFaker;

class DormitoryRoomsApiTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $adminUser;
    protected $dormitory;
    protected $roomType;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create admin role
        $adminRole = Role::create(['name' => 'admin']);
        
        // Create dormitory
        $this->dormitory = Dormitory::create([
            'name' => 'A Block',
            'capacity' => 100,
            'gender' => 'mixed',
            'address' => 'Test Address',
            'description' => 'Test Description',
            'phone' => '+1234567890'
        ]);
        
        // Create room type
        $this->roomType = RoomType::create([
            'name' => 'standard',
            'capacity' => 2,
            'price' => 150.00,
            'beds' => json_encode([
                ['x' => 10, 'y' => 20, 'width' => 30, 'height' => 40],
                ['x' => 50, 'y' => 60, 'width' => 30, 'height' => 40]
            ])
        ]);
        
        // Create admin user
        $this->adminUser = User::create([
            'name' => 'Admin User',
            'email' => 'admin@email.com',
            'password' => bcrypt('supersecret'),
            'status' => 'active',
            'role_id' => $adminRole->id
        ]);
        
        // Create admin profile with dormitory assignment
        AdminProfile::create([
            'user_id' => $this->adminUser->id,
            'dormitory_id' => $this->dormitory->id,
            'office_phone' => '+1234567890'
        ]);
    }

    #[Test]
    public function it_can_fetch_rooms_for_dormitory_via_api()
    {
        // Create rooms with beds for the dormitory
        $room1 = Room::factory()->create([
            'number' => '101',
            'dormitory_id' => $this->dormitory->id,
            'floor' => 1,
            'notes' => 'First floor room',
            'room_type_id' => $this->roomType->id,
            'quota' => 2
        ]);

        $room2 = Room::factory()->create([
            'number' => '102',
            'dormitory_id' => $this->dormitory->id,
            'floor' => 1,
            'notes' => 'Second floor room',
            'room_type_id' => $this->roomType->id,
            'quota' => 2
        ]);

        // Test the API endpoint
        $response = $this->getJson("/api/dormitories/{$this->dormitory->id}/rooms");

        $response->assertStatus(200)
                ->assertJsonCount(2)
                ->assertJsonStructure([
                    '*' => [
                        'id',
                        'number',
                        'floor',
                        'notes',
                        'dormitory_id',
                        'room_type_id',
                        'quota',
                        'room_type' => [
                            'id',
                            'name',
                            'capacity',
                            'price'
                        ],
                        'beds' => [
                            '*' => [
                                'id',
                                'room_id',
                                'bed_number',
                                'is_occupied',
                                'reserved_for_staff'
                            ]
                        ]
                    ]
                ]);

        // Verify specific room data
        $response->assertJsonFragment([
            'number' => '101',
            'floor' => 1,
            'dormitory_id' => $this->dormitory->id
        ]);

        $response->assertJsonFragment([
            'number' => '102',
            'floor' => 1,
            'dormitory_id' => $this->dormitory->id
        ]);

        // Verify beds are included
        $rooms = $response->json();
        $this->assertEquals(2, count($rooms[0]['beds'])); // Room 101 has 2 beds
        $this->assertEquals(2, count($rooms[1]['beds'])); // Room 102 has 2 beds
    }

    #[Test]
    public function it_returns_empty_array_for_dormitory_with_no_rooms()
    {
        $response = $this->getJson("/api/dormitories/{$this->dormitory->id}/rooms");

        $response->assertStatus(200)
                ->assertJsonCount(0);
    }

    #[Test]
    public function it_returns_404_for_nonexistent_dormitory()
    {
        $response = $this->getJson("/api/dormitories/99999/rooms");

        $response->assertStatus(404);
    }
    
    #[Test]
    public function it_includes_room_type_and_beds_relationships()
    {
        // Create a room with beds
        $room = Room::factory()->create([
            'number' => '201',
            'dormitory_id' => $this->dormitory->id,
            'floor' => 2,
            'notes' => 'Second floor room',
            'room_type_id' => $this->roomType->id,
            'quota' => 2
        ]);

        $response = $this->getJson("/api/dormitories/{$this->dormitory->id}/rooms");

        $response->assertStatus(200)
                ->assertJsonCount(1);

        $roomData = $response->json()[0];
        
        // Verify room type is loaded
        $this->assertArrayHasKey('room_type', $roomData);
        $this->assertEquals($this->roomType->id, $roomData['room_type']['id']);
        $this->assertEquals('standard', $roomData['room_type']['name']);
        
        // Verify beds are loaded
        $this->assertArrayHasKey('beds', $roomData);
        $this->assertCount(2, $roomData['beds']); // The factory creates 2 beds based on RoomType capacity
        $bed = $room->beds()->first(); // Get a reference to the created bed
        $this->assertEquals($bed->id, $roomData['beds'][0]['id']);
        $this->assertEquals(1, $roomData['beds'][0]['bed_number']);
        $this->assertFalse($roomData['beds'][0]['is_occupied']);
        $this->assertFalse($roomData['beds'][0]['reserved_for_staff']);
    }

    #[Test]
    public function it_handles_rooms_with_different_occupancy_statuses()
    {
        // Create a room with mixed bed occupancy
        $room = Room::factory()->create([
            'number' => '301',
            'dormitory_id' => $this->dormitory->id,
            'floor' => 3,
            'notes' => 'Mixed occupancy room',
            'room_type_id' => $this->roomType->id,
            'quota' => 2
        ]);
        // Manually occupy one bed
        $room->beds()->first()->update(['is_occupied' => true]);

        $response = $this->getJson("/api/dormitories/{$this->dormitory->id}/rooms");

        $response->assertStatus(200);
        
        $roomData = $response->json()[0];
        $this->assertCount(2, $roomData['beds']);
        
        // Find occupied bed
        $occupiedBedData = collect($roomData['beds'])->firstWhere('bed_number', 1);
        $this->assertTrue($occupiedBedData['is_occupied']);
        
        // Find available bed
        $availableBedData = collect($roomData['beds'])->firstWhere('bed_number', 2);
        $this->assertFalse($availableBedData['is_occupied']);
    }

    #[Test]
    public function it_handles_staff_reserved_beds()
    {
        // Create a room with staff reserved bed
        $room = Room::factory()->create([
            'number' => '401',
            'dormitory_id' => $this->dormitory->id,
            'floor' => 4,
            'notes' => 'Staff room',
            'room_type_id' => $this->roomType->id,
            'quota' => 2
        ]);

        // Manually reserve one bed for staff
        $room->beds()->first()->update(['reserved_for_staff' => true]);

        $response = $this->getJson("/api/dormitories/{$this->dormitory->id}/rooms");

        $response->assertStatus(200);
        
        $roomData = $response->json()[0];
        $this->assertCount(2, $roomData['beds']);
        
        // Find staff reserved bed
        $staffBedData = collect($roomData['beds'])->firstWhere('bed_number', 1);
        $this->assertTrue($staffBedData['reserved_for_staff']);
        
        // Find regular bed
        $regularBedData = collect($roomData['beds'])->firstWhere('bed_number', 2);
        $this->assertFalse($regularBedData['reserved_for_staff']);
    }

    #[Test]
    public function it_returns_rooms_with_correct_dormitory_assignment()
    {
        // Create another dormitory
        $dormitory2 = Dormitory::create([
            'name' => 'B Block',
            'capacity' => 50,
            'gender' => 'female',
            'address' => 'Test Address 2',
            'description' => 'Test Description 2',
            'phone' => '+1234567891'
        ]);
        
        // Create room in first dormitory
        $room1 = Room::factory()->create([
            'number' => '101',
            'dormitory_id' => $this->dormitory->id,
            'floor' => 1,
            'notes' => 'Room in A Block',
            'room_type_id' => $this->roomType->id,
            'quota' => 2
        ]);
        
        // Create room in second dormitory
        $room2 = Room::factory()->create([
            'number' => '201',
            'dormitory_id' => $dormitory2->id,
            'floor' => 2,
            'notes' => 'Room in B Block',
            'room_type_id' => $this->roomType->id,
            'quota' => 2
        ]);

        // Test first dormitory
        $response1 = $this->getJson("/api/dormitories/{$this->dormitory->id}/rooms");
        $response1->assertStatus(200)
                 ->assertJsonCount(1)
                 ->assertJsonFragment(['number' => '101'])
                 ->assertJsonMissing(['number' => '201']);

        // Test second dormitory
        $response2 = $this->getJson("/api/dormitories/{$dormitory2->id}/rooms");
        $response2->assertStatus(200)
                 ->assertJsonCount(1)
                 ->assertJsonFragment(['number' => '201'])
                 ->assertJsonMissing(['number' => '101']);
    }
}
