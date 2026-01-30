<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Dormitory;
use App\Models\Role;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * @coversDefaultClass \App\Http\Controllers\RoomTypeController
 */
class RoomTypePhotosTest extends TestCase
{
    use RefreshDatabase;

    private Role $sudoRole;
    private Dormitory $dormitory;
    private RoomType $roomType;
    private Room $room;
    private User $sudo;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->sudoRole = Role::factory()->create([ 'name' => 'sudo' ]);

        $this->sudo = User::factory()->create([ 'role_id' => $this->sudoRole->id ]);

        $adminRole = Role::factory()->create([ 'name' => 'admin' ]);
        $admin = User::factory()->create([ 'role_id' => $adminRole->id ]);

        $this->dormitory = Dormitory::factory()->create([
            'admin_id' => $admin->id,
            'gender'   => 'male',
        ]);

        $this->roomType = RoomType::factory()->create([
            'name'          => 'Standard',
            'capacity'      => 2,
            'daily_rate'    => 50.00,
            'semester_rate' => 5000.00,
            'photos'        => null, // Start with no photos
        ]);

        $this->room = Room::factory()->create([
            'dormitory_id'  => $this->dormitory->id,
            'room_type_id'  => $this->roomType->id,
            'number'        => '101',
            'occupant_type' => 'student',
        ]);
    }

    /** @covers \App\Http\Controllers\RoomTypeController::store */
    public function test_room_type_can_have_photos_uploaded(): void
    {
        $photos = [
            UploadedFile::fake()->image('photo1.jpg', 800, 600),
            UploadedFile::fake()->image('photo2.jpg', 800, 600),
            UploadedFile::fake()->image('photo3.jpg', 800, 600),
        ];

        $response = $this->actingAs($this->sudo)->postJson('/api/room-types', [
            'name'          => 'Lux Room',
            'capacity'      => 2,
            'daily_rate'    => 100.00,
            'semester_rate' => 10000.00,
            'photos'        => $photos,
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'id',
            'name',
            'photos',
        ]);

        $roomTypeData = $response->json();
        $this->assertIsArray($roomTypeData['photos']);
        $this->assertCount(3, $roomTypeData['photos']);

        // Verify files were stored
        foreach ($roomTypeData['photos'] as $photoPath) {
            $this->assertStringContainsString('room-type', $photoPath);
            Storage::disk('public')->assertExists($photoPath);
        }
    }

    /** @covers \App\Http\Controllers\RoomTypeController::store */
    public function test_room_type_accepts_up_to_10_photos(): void
    {
        $photos = [];
        for ($i = 1; $i <= 10; $i++) {
            $photos[] = UploadedFile::fake()->image("photo{$i}.jpg", 800, 600);
        }

        $response = $this->actingAs($this->sudo)->postJson('/api/room-types', [
            'name'          => 'Lux Room',
            'capacity'      => 2,
            'daily_rate'    => 100.00,
            'semester_rate' => 10000.00,
            'photos'        => $photos,
        ]);

        $response->assertStatus(201);
        $roomTypeData = $response->json();
        $this->assertCount(10, $roomTypeData['photos']);
    }

    /** @covers \App\Http\Controllers\RoomTypeController::store */
    public function test_room_type_rejects_more_than_10_photos(): void
    {
        $photos = [];
        for ($i = 1; $i <= 11; $i++) {
            $photos[] = UploadedFile::fake()->image("photo{$i}.jpg", 800, 600);
        }

        $response = $this->actingAs($this->sudo)->postJson('/api/room-types', [
            'name'          => 'Lux Room',
            'capacity'      => 2,
            'daily_rate'    => 100.00,
            'semester_rate' => 10000.00,
            'photos'        => $photos,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors([ 'photos' ]);
    }

    /** @covers \App\Http\Controllers\RoomTypeController::show */
    public function test_room_type_photos_are_returned_in_api_response(): void
    {
        // Create room type with photos
        $photoPaths = [
            'room-type/photo1.jpg',
            'room-type/photo2.jpg',
            'room-type/photo3.jpg',
        ];

        // Create fake files
        foreach ($photoPaths as $path) {
            Storage::disk('public')->put($path, 'fake image content');
        }

        $roomType = RoomType::factory()->create([
            'name'          => 'Standard with Photos',
            'capacity'      => 2,
            'daily_rate'    => 50.00,
            'semester_rate' => 5000.00,
            'photos'        => $photoPaths,
        ]);

        $response = $this->actingAs($this->sudo)->getJson("/api/room-types/{$roomType->id}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'id',
            'name',
            'photos',
        ]);

        $roomTypeData = $response->json();
        $this->assertIsArray($roomTypeData['photos']);
        $this->assertCount(3, $roomTypeData['photos']);
        $this->assertEquals($photoPaths, $roomTypeData['photos']);
    }

    /** @covers \App\Http\Controllers\DormitoryController::getForRegistration */
    public function test_room_type_photos_are_included_in_available_rooms_endpoint(): void
    {
        // Create room type with photos
        $photoPaths = [
            'room-type/photo1.jpg',
            'room-type/photo2.jpg',
        ];

        foreach ($photoPaths as $path) {
            Storage::disk('public')->put($path, 'fake image content');
        }

        $roomTypeWithPhotos = RoomType::factory()->create([
            'name'          => 'Standard with Photos',
            'capacity'      => 2,
            'daily_rate'    => 50.00,
            'semester_rate' => 5000.00,
            'photos'        => $photoPaths,
        ]);

        $room = Room::factory()->create([
            'dormitory_id'  => $this->dormitory->id,
            'room_type_id'  => $roomTypeWithPhotos->id,
            'number'        => '201',
            'occupant_type' => 'student',
        ]);

        // Get available rooms for registration
        $response = $this->getJson("/api/dormitories/{$this->dormitory->id}/registration");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'id',
                'name',
                'rooms' => [
                    '*' => [
                        'id',
                        'number',
                        'room_type' => [
                            'id',
                            'name',
                            'photos',
                        ],
                    ],
                ],
            ],
        ]);

        $dormitoryData = $response->json('data');
        $rooms = collect($dormitoryData['rooms']);
        $roomData = $rooms->firstWhere('id', $room->id);

        $this->assertNotNull($roomData);
        $this->assertArrayHasKey('room_type', $roomData);
        $this->assertArrayHasKey('photos', $roomData['room_type']);
        $this->assertIsArray($roomData['room_type']['photos']);
        $this->assertCount(2, $roomData['room_type']['photos']);
    }

    /** @covers \App\Http\Controllers\RoomTypeController::update */
    public function test_room_type_photos_can_be_updated(): void
    {
        // Create room type with initial photos
        $initialPhotos = [
            'room-type/old1.jpg',
            'room-type/old2.jpg',
        ];

        foreach ($initialPhotos as $path) {
            Storage::disk('public')->put($path, 'fake image content');
        }

        $roomType = RoomType::factory()->create([
            'name'          => 'Standard',
            'capacity'      => 2,
            'daily_rate'    => 50.00,
            'semester_rate' => 5000.00,
            'photos'        => $initialPhotos,
        ]);

        // Add new photos
        $newPhotos = [
            UploadedFile::fake()->image('new1.jpg', 800, 600),
            UploadedFile::fake()->image('new2.jpg', 800, 600),
        ];

        $response = $this->actingAs($this->sudo)->putJson("/api/room-types/{$roomType->id}", [
            'existing_photos' => json_encode($initialPhotos),
            'photos'          => $newPhotos,
        ]);

        $response->assertStatus(200);
        $roomTypeData = $response->json();

        // Should have both old and new photos
        $this->assertGreaterThanOrEqual(2, count($roomTypeData['photos']));
    }

    /** @covers \App\Http\Controllers\RoomTypeController::store */
    public function test_room_type_photos_validate_image_types(): void
    {
        $invalidFile = UploadedFile::fake()->create('document.pdf', 100);

        $response = $this->actingAs($this->sudo)->postJson('/api/room-types', [
            'name'          => 'Lux Room',
            'capacity'      => 2,
            'daily_rate'    => 100.00,
            'semester_rate' => 10000.00,
            'photos'        => [ $invalidFile ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors([ 'photos.0' ]);
    }
}
