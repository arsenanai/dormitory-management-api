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
use Illuminate\Foundation\Testing\WithFaker;

class StudentCreationRoomAvailabilityTest extends TestCase
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
        
        // Create dormitory (A Block - ID 3 as used in frontend)
        $this->dormitory = Dormitory::create([
            'id' => 3, // Match the ID used in frontend tests
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

    /** @test */
    public function it_provides_rooms_with_available_beds_for_student_creation()
    {
        // Create multiple rooms with different bed availability scenarios
        
        // Room 1: All beds available
        $room1 = Room::create([
            'number' => '101',
            'dormitory_id' => $this->dormitory->id,
            'floor' => 1,
            'notes' => 'All beds available',
            'room_type_id' => $this->roomType->id,
            'quota' => 2
        ]);
        
        $bed1_1 = Bed::create([
            'room_id' => $room1->id,
            'bed_number' => 1,
            'is_occupied' => false,
            'reserved_for_staff' => false
        ]);
        
        $bed1_2 = Bed::create([
            'room_id' => $room1->id,
            'bed_number' => 2,
            'is_occupied' => false,
            'reserved_for_staff' => false
        ]);
        
        // Room 2: One bed available, one occupied
        $room2 = Room::create([
            'number' => '102',
            'dormitory_id' => $this->dormitory->id,
            'floor' => 1,
            'notes' => 'Mixed availability',
            'room_type_id' => $this->roomType->id,
            'quota' => 2
        ]);
        
        $bed2_1 = Bed::create([
            'room_id' => $room2->id,
            'bed_number' => 1,
            'is_occupied' => true, // Occupied
            'reserved_for_staff' => false
        ]);
        
        $bed2_2 = Bed::create([
            'room_id' => $room2->id,
            'bed_number' => 2,
            'is_occupied' => false, // Available
            'reserved_for_staff' => false
        ]);
        
        // Room 3: All beds occupied
        $room3 = Room::create([
            'number' => '103',
            'dormitory_id' => $this->dormitory->id,
            'floor' => 1,
            'notes' => 'All beds occupied',
            'room_type_id' => $this->roomType->id,
            'quota' => 2
        ]);
        
        $bed3_1 = Bed::create([
            'room_id' => $room3->id,
            'bed_number' => 1,
            'is_occupied' => true,
            'reserved_for_staff' => false
        ]);
        
        $bed3_2 = Bed::create([
            'room_id' => $room3->id,
            'bed_number' => 2,
            'is_occupied' => true,
            'reserved_for_staff' => false
        ]);
        
        // Room 4: Staff reserved beds (should not be available for students)
        $room4 = Room::create([
            'number' => '104',
            'dormitory_id' => $this->dormitory->id,
            'floor' => 1,
            'notes' => 'Staff reserved',
            'room_type_id' => $this->roomType->id,
            'quota' => 2
        ]);
        
        $bed4_1 = Bed::create([
            'room_id' => $room4->id,
            'bed_number' => 1,
            'is_occupied' => false,
            'reserved_for_staff' => true // Staff reserved
        ]);
        
        $bed4_2 = Bed::create([
            'room_id' => $room4->id,
            'bed_number' => 2,
            'is_occupied' => false,
            'reserved_for_staff' => true // Staff reserved
        ]);

        // Test the API endpoint
        $response = $this->getJson("/api/dormitories/{$this->dormitory->id}/rooms");

        $response->assertStatus(200)
                ->assertJsonCount(4); // Should return all 4 rooms

        $rooms = $response->json();
        
        // Verify room 1 has 2 available beds
        $room1Data = collect($rooms)->firstWhere('number', '101');
        $this->assertNotNull($room1Data);
        $this->assertCount(2, $room1Data['beds']);
        $this->assertFalse($room1Data['beds'][0]['is_occupied']);
        $this->assertFalse($room1Data['beds'][0]['reserved_for_staff']);
        $this->assertFalse($room1Data['beds'][1]['is_occupied']);
        $this->assertFalse($room1Data['beds'][1]['reserved_for_staff']);
        
        // Verify room 2 has 1 available bed
        $room2Data = collect($rooms)->firstWhere('number', '102');
        $this->assertNotNull($room2Data);
        $this->assertCount(2, $room2Data['beds']);
        $this->assertTrue($room2Data['beds'][0]['is_occupied']); // Bed 1 occupied
        $this->assertFalse($room2Data['beds'][1]['is_occupied']); // Bed 2 available
        
        // Verify room 3 has no available beds
        $room3Data = collect($rooms)->firstWhere('number', '103');
        $this->assertNotNull($room3Data);
        $this->assertCount(2, $room3Data['beds']);
        $this->assertTrue($room3Data['beds'][0]['is_occupied']);
        $this->assertTrue($room3Data['beds'][1]['is_occupied']);
        
        // Verify room 4 has staff reserved beds
        $room4Data = collect($rooms)->firstWhere('number', '104');
        $this->assertNotNull($room4Data);
        $this->assertCount(2, $room4Data['beds']);
        $this->assertTrue($room4Data['beds'][0]['reserved_for_staff']);
        $this->assertTrue($room4Data['beds'][1]['reserved_for_staff']);
    }

    /** @test */
    public function it_returns_rooms_with_correct_structure_for_frontend_consumption()
    {
        // Create a room with beds
        $room = Room::create([
            'number' => '201',
            'dormitory_id' => $this->dormitory->id,
            'floor' => 2,
            'notes' => 'Test room for frontend',
            'room_type_id' => $this->roomType->id,
            'quota' => 2
        ]);
        
        $bed = Bed::create([
            'room_id' => $room->id,
            'bed_number' => 1,
            'is_occupied' => false,
            'reserved_for_staff' => false
        ]);

        $response = $this->getJson("/api/dormitories/{$this->dormitory->id}/rooms");

        $response->assertStatus(200)
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

        // Verify the data structure matches what frontend expects
        $roomData = $response->json()[0];
        
        // Room data
        $this->assertEquals('201', $roomData['number']);
        $this->assertEquals(2, $roomData['floor']);
        $this->assertEquals($this->dormitory->id, $roomData['dormitory_id']);
        $this->assertEquals($this->roomType->id, $roomData['room_type_id']);
        $this->assertEquals(2, $roomData['quota']);
        
        // Room type data
        $this->assertEquals('standard', $roomData['room_type']['name']);
        $this->assertEquals(2, $roomData['room_type']['capacity']);
        $this->assertEquals(150.00, $roomData['room_type']['price']);
        
        // Bed data
        $this->assertCount(1, $roomData['beds']);
        $bedData = $roomData['beds'][0];
        $this->assertEquals($bed->id, $bedData['id']);
        $this->assertEquals($room->id, $bedData['room_id']);
        $this->assertEquals(1, $bedData['bed_number']);
        $this->assertFalse($bedData['is_occupied']);
        $this->assertFalse($bedData['reserved_for_staff']);
    }

    /** @test */
    public function it_handles_dormitory_with_no_rooms_gracefully()
    {
        // Test with dormitory that has no rooms
        $response = $this->getJson("/api/dormitories/{$this->dormitory->id}/rooms");

        $response->assertStatus(200)
                ->assertJsonCount(0)
                ->assertJson([]);
    }

    /** @test */
    public function it_returns_404_for_invalid_dormitory_id()
    {
        $response = $this->getJson("/api/dormitories/99999/rooms");

        $response->assertStatus(404);
    }

    /** @test */
    public function it_provides_rooms_suitable_for_student_assignment()
    {
        // Create rooms with different scenarios to test student assignment suitability
        
        // Room 1: Perfect for student assignment (2 available beds)
        $room1 = Room::create([
            'number' => '301',
            'dormitory_id' => $this->dormitory->id,
            'floor' => 3,
            'notes' => 'Perfect for students',
            'room_type_id' => $this->roomType->id,
            'quota' => 2
        ]);
        
        Bed::create([
            'room_id' => $room1->id,
            'bed_number' => 1,
            'is_occupied' => false,
            'reserved_for_staff' => false
        ]);
        
        Bed::create([
            'room_id' => $room1->id,
            'bed_number' => 2,
            'is_occupied' => false,
            'reserved_for_staff' => false
        ]);
        
        // Room 2: Partially available (1 available bed)
        $room2 = Room::create([
            'number' => '302',
            'dormitory_id' => $this->dormitory->id,
            'floor' => 3,
            'notes' => 'Partially available',
            'room_type_id' => $this->roomType->id,
            'quota' => 2
        ]);
        
        Bed::create([
            'room_id' => $room2->id,
            'bed_number' => 1,
            'is_occupied' => true, // Occupied
            'reserved_for_staff' => false
        ]);
        
        Bed::create([
            'room_id' => $room2->id,
            'bed_number' => 2,
            'is_occupied' => false, // Available
            'reserved_for_staff' => false
        ]);

        $response = $this->getJson("/api/dormitories/{$this->dormitory->id}/rooms");

        $response->assertStatus(200)
                ->assertJsonCount(2);

        $rooms = $response->json();
        
        // Verify room 1 has 2 available beds (perfect for student assignment)
        $room1Data = collect($rooms)->firstWhere('number', '301');
        $availableBeds1 = collect($room1Data['beds'])->where('is_occupied', false)->where('reserved_for_staff', false);
        $this->assertEquals(2, $availableBeds1->count());
        
        // Verify room 2 has 1 available bed (suitable for single student)
        $room2Data = collect($rooms)->firstWhere('number', '302');
        $availableBeds2 = collect($room2Data['beds'])->where('is_occupied', false)->where('reserved_for_staff', false);
        $this->assertEquals(1, $availableBeds2->count());
        
        // Total available beds should be 3
        $totalAvailableBeds = collect($rooms)->flatMap(function($room) {
            return $room['beds'];
        })->where('is_occupied', false)->where('reserved_for_staff', false)->count();
        $this->assertEquals(3, $totalAvailableBeds);
    }
}
