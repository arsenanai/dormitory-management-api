<?php

namespace Tests\Unit\Services;

use App\Models\Bed;
use App\Models\GuestProfile;
use App\Models\Role;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use App\Services\GuestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuestServiceTest extends TestCase
{
    use RefreshDatabase;

    private GuestService $guestService;
    private User $guestUser;
    private Room $room;
    private RoomType $roomType;
    private Bed $bed;

    protected function setUp(): void
    {
        parent::setUp();

        $this->guestService = new GuestService();

        // Create guest role
        $guestRole = Role::factory()->create(['name' => 'guest']);

        // Create room type
        $this->roomType = RoomType::factory()->create([
            'name' => 'Test Room Type',
            'capacity' => 2,
            'daily_rate' => 50.00,
            'semester_rate' => 1500.00,
            'beds' => [
                ['id' => '1', 'x' => 10, 'y' => 10, 'width' => 50, 'height' => 80, 'rotation' => 0],
                ['id' => '2', 'x' => 70, 'y' => 10, 'width' => 50, 'height' => 80, 'rotation' => 0]
            ]
        ]);

        // Create room
        $this->room = Room::factory()->create([
            'number' => '101',
            'room_type_id' => $this->roomType->id,
            'dormitory_id' => 1
        ]);

        // Create beds
        $this->bed = Bed::factory()->create([
            'bed_number' => 1,
            'room_id' => $this->room->id,
            'is_occupied' => false
        ]);

        // Create guest user
        $this->guestUser = User::factory()->create([
            'role_id' => $guestRole->id,
            'room_id' => $this->room->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'phone' => '+1234567890'
        ]);

        // Create guest profile
        GuestProfile::factory()->create([
            'user_id' => $this->guestUser->id,
            'bed_id' => $this->bed->id,
            'purpose_of_visit' => 'Business',
            'visit_start_date' => now()->addDays(1),
            'visit_end_date' => now()->addDays(7),
            'daily_rate' => 50.00,
            'is_approved' => true
        ]);
    }

    public function test_get_guests_with_filters_returns_guests_with_relationships()
    {
        $filters = [];
        $result = $this->guestService->getGuestsWithFilters($filters);

        $this->assertNotEmpty($result);
        $guest = $result->first();

        $this->assertTrue($guest->relationLoaded('guestProfile'));
        $this->assertTrue($guest->relationLoaded('room'));
        $this->assertTrue($guest->room->relationLoaded('dormitory'));
        $this->assertTrue($guest->room->relationLoaded('roomType'));
    }

    public function test_get_guests_with_filters_filters_by_room_id()
    {
        // Create another room and guest
        $anotherRoomType = RoomType::factory()->create();
        $anotherRoom = Room::factory()->create(['room_type_id' => $anotherRoomType->id]);
        $anotherBed = Bed::factory()->create(['room_id' => $anotherRoom->id]);

        $anotherGuest = User::factory()->create(['room_id' => $anotherRoom->id]);
        GuestProfile::factory()->create(['user_id' => $anotherGuest->id, 'bed_id' => $anotherBed->id]);

        $filters = ['room_id' => $this->room->id];
        $result = $this->guestService->getGuestsWithFilters($filters);

        $this->assertCount(1, $result);
        $this->assertEquals($this->guestUser->id, $result->first()->id);
    }

    public function test_get_guests_with_filters_includes_room_type_beds_data()
    {
        $filters = [];
        $result = $this->guestService->getGuestsWithFilters($filters);

        $guest = $result->first();
        $roomType = $guest->room->roomType;

        $this->assertNotNull($roomType->beds);
        $this->assertIsArray($roomType->beds);
        $this->assertCount(2, $roomType->beds);
    }

    public function test_create_guest_calculates_daily_rate_correctly()
    {
        $guestData = [
            'name' => 'Jane Smith',
            'email' => 'jane.smith@example.com',
            'phone' => '+0987654321',
            'room_id' => $this->room->id,
            'check_in_date' => now()->addDays(1)->toDateString(),
            'check_out_date' => now()->addDays(8)->toDateString(),
            'bed_id' => $this->bed->id,
            'total_amount' => 350.00,
            'notes' => 'Test guest'
        ];

        $guest = $this->guestService->createGuest($guestData);

        $this->assertNotNull($guest);
        $this->assertEquals('Jane Smith', $guest->name);
        $this->assertEquals('jane.smith@example.com', $guest->email);
        $this->assertEquals($this->room->id, $guest->room_id);
        $this->assertEquals($this->bed->id, $guest->guestProfile->bed_id);
    }

    public function test_create_guest_uses_room_type_daily_rate_as_fallback()
    {
        $guestData = [
            'name' => 'Bob Johnson',
            'email' => 'bob.johnson@example.com',
            'phone' => '+1122334455',
            'room_id' => $this->room->id,
            'check_in_date' => now()->addDays(1)->toDateString(),
            'check_out_date' => now()->addDays(3)->toDateString(),
            'bed_id' => $this->bed->id,
            'total_amount' => 0, // Will be calculated
            'notes' => 'Test guest with calculated rate'
        ];

        $guest = $this->guestService->createGuest($guestData);

        // Should calculate 2 days * 50.00 = 100.00
        $this->assertEquals(100.00, $guest->guestProfile->daily_rate);
    }

    public function test_get_available_rooms_returns_rooms_with_room_type()
    {
        $result = $this->guestService->getAvailableRooms();

        $this->assertNotEmpty($result);
        $room = $result->first();

        $this->assertTrue($room->relationLoaded('dormitory'));
        $this->assertTrue($room->relationLoaded('roomType'));
        $this->assertFalse($room->is_occupied);
    }

    public function test_get_available_rooms_excludes_occupied_rooms()
    {
        // Mark the room as occupied
        $this->room->update(['is_occupied' => true]);

        $result = $this->guestService->getAvailableRooms();

        $this->assertEmpty($result);
    }

    public function test_export_guests_includes_room_type_data()
    {
        $filters = [];
        $result = $this->guestService->exportGuests($filters);

        $this->assertNotEmpty($result);
        $guest = $result->first();

        $this->assertTrue($guest->relationLoaded('guestProfile'));
        $this->assertTrue($guest->relationLoaded('room'));
        $this->assertTrue($guest->room->relationLoaded('dormitory'));
        $this->assertTrue($guest->room->relationLoaded('roomType'));
    }

    public function test_export_guests_filters_by_room_id()
    {
        // Create another room and guest
        $anotherRoomType = RoomType::factory()->create();
        $anotherRoom = Room::factory()->create(['room_type_id' => $anotherRoomType->id]);
        $anotherBed = Bed::factory()->create(['room_id' => $anotherRoom->id]);

        $anotherGuest = User::factory()->create(['room_id' => $anotherRoom->id]);
        GuestProfile::factory()->create(['user_id' => $anotherGuest->id, 'bed_id' => $anotherBed->id]);

        $filters = ['room_id' => $this->room->id];
        $result = $this->guestService->exportGuests($filters);

        $this->assertCount(1, $result);
        $this->assertEquals($this->guestUser->id, $result->first()->id);
    }

    public function test_get_guest_by_id_returns_guest_with_relationships()
    {
        $guest = $this->guestService->getGuestById($this->guestUser->id);

        $this->assertNotNull($guest);
        $this->assertEquals($this->guestUser->id, $guest->id);
        $this->assertTrue($guest->relationLoaded('guestProfile'));
        $this->assertTrue($guest->relationLoaded('room'));
        $this->assertTrue($guest->room->relationLoaded('dormitory'));
        $this->assertTrue($guest->room->relationLoaded('roomType'));
    }

    public function test_get_guest_by_id_throws_exception_for_nonexistent_guest()
    {
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $this->guestService->getGuestById(99999);
    }

    public function test_update_guest_updates_guest_data()
    {
        $updateData = [
            'name' => 'Updated Name',
            'email' => 'updated.email@example.com',
            'phone' => '+9988776655'
        ];

        $updatedGuest = $this->guestService->updateGuest($this->guestUser->id, $updateData);

        $this->assertEquals('Updated Name', $updatedGuest->name);
        $this->assertEquals('updated.email@example.com', $updatedGuest->email);
        $this->assertEquals('+9988776655', $updatedGuest->phone);
    }

    public function test_delete_guest_removes_guest()
    {
        $this->assertTrue($this->guestService->deleteGuest($this->guestUser->id));

        $this->assertDatabaseMissing('users', ['id' => $this->guestUser->id]);
        $this->assertDatabaseMissing('guest_profiles', ['user_id' => $this->guestUser->id]);
    }

    public function test_delete_guest_throws_exception_for_nonexistent_guest()
    {
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $this->guestService->deleteGuest(99999);
    }
}
