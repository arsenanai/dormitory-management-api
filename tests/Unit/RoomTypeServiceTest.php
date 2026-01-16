<?php

namespace Tests\Unit;

use App\Models\RoomType;
use App\Services\FileService;
use App\Services\RoomTypeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Uses;
use Tests\TestCase;

#[CoversClass(RoomTypeService::class)]
class RoomTypeServiceTest extends TestCase
{
    use RefreshDatabase;

    private RoomTypeService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $fileService = new FileService();
        $this->service = new RoomTypeService($fileService);
        Storage::fake('public');
    }

    #[Test]
    public function it_creates_room_type_without_photos()
    {
        // Create room type without photos
        $data = [
            'name' => 'Test Room',
            'capacity' => 2,
            'daily_rate' => 100.00,
            'semester_rate' => 3000.00,
        ];
        $request = new \Illuminate\Http\Request();
        $result = $this->service->createRoomType($data, $request);

        // Assertions
        $this->assertNotNull($result);
        $this->assertEquals('Test Room', $result->name);
        $this->assertEquals(2, $result->capacity);
        $this->assertEmpty($result->photos);
    }

    #[Test]
    #[Uses(FileService::class)]
    public function it_creates_room_type_with_photos()
    {
        // Create fake files
        $photo1 = UploadedFile::fake()->image('room1.jpg');
        $photo2 = UploadedFile::fake()->image('room2.jpg');

        // Create mock request with files
        $request = new \Illuminate\Http\Request();
        $request->files->set('photo_0', $photo1);
        $request->files->set('photo_1', $photo2);

        // Create room type
        $data = [
            'name' => 'Test Room',
            'capacity' => 2,
            'daily_rate' => 100.00,
            'semester_rate' => 3000.00,
            'photos' => json_encode([[], []]),
        ];
        $result = $this->service->createRoomType($data, $request);

        // Assertions
        $this->assertNotNull($result);
        $this->assertNotNull($result->photos);
        $this->assertIsArray($result->photos);
        $this->assertCount(2, $result->photos);

        // Files should be stored in public disk with correct paths
        $this->assertStringStartsWith('room-type/', $result->photos[0]);
        $this->assertStringEndsWith('.jpg', $result->photos[0]);

        // Files should exist in storage
        Storage::disk('public')->assertExists($result->photos[0]);
        Storage::disk('public')->assertExists($result->photos[1]);
    }

    #[Test]
    public function it_updates_room_type_without_photos()
    {
        // Create room type without photos
        $roomType = RoomType::create([
            'name' => 'Test Room',
            'capacity' => 2,
        ]);

        // Update room type without photos
        $request = new \Illuminate\Http\Request();
        $data = ['name' => 'Updated Room'];
        $result = $this->service->updateRoomType($roomType, $data, $request);

        // Assertions
        $this->assertEquals('Updated Room', $result->name);
        $this->assertEmpty($result->photos);
    }

    #[Test]
    public function it_deletes_room_type_without_photos()
    {
        // Create room type without photos
        $roomType = RoomType::create([
            'name' => 'Test Room',
            'capacity' => 2,
        ]);

        // Delete room type
        $this->service->deleteRoomType($roomType->id);

        // Verify deletion
        $this->assertDatabaseMissing('room_types', ['id' => $roomType->id]);
    }

    #[Test]
    #[Uses(FileService::class)]
    public function it_deletes_room_type_with_photos()
    {
        // Create room type with photos
        $roomType = RoomType::create([
            'name' => 'Test Room',
            'photos' => ['room-type/room-photo1.jpg', 'room-type/room-photo2.jpg'],
            'capacity' => 2,
        ]);

        // Create fake files
        Storage::disk('public')->put('room-type/room-photo1.jpg', 'content 1');
        Storage::disk('public')->put('room-type/room-photo2.jpg', 'content 2');

        // Delete room type
        $this->service->deleteRoomType($roomType->id);

        // Files should be deleted
        Storage::disk('public')->assertMissing('room-type/room-photo1.jpg');
        Storage::disk('public')->assertMissing('room-type/room-photo2.jpg');
    }

    #[Test]
    #[Uses(FileService::class)]
    public function it_creates_room_type_with_photos_in_room_type_folder()
    {
        $request = new Request();
        $request->files->set('photo_0', UploadedFile::fake()->image('room-photo1.jpg'));
        $request->files->set('photo_1', UploadedFile::fake()->image('room-photo2.png'));
        $request->merge(['photos' => json_encode([[], []])]);

        $data = [
            'name' => 'Test Room',
            'capacity' => 2,
            'daily_rate' => 50.00,
            'semester_rate' => 1500.00,
        ];

        $roomType = $this->service->createRoomType($data, $request);

        $this->assertNotNull($roomType->photos);
        $this->assertCount(2, $roomType->photos);
        $this->assertStringContainsString('room-type', $roomType->photos[0]);
        $this->assertStringContainsString('room-type', $roomType->photos[1]);

        // Verify files are stored in room-type folder
        Storage::disk('public')->assertExists($roomType->photos[0]);
        Storage::disk('public')->assertExists($roomType->photos[1]);
    }

    #[Test]
    #[Uses(FileService::class)]
    public function it_updates_room_type_photos_with_cleanup()
    {
        // Create room type with existing photos
        $createRequest = new Request();
        $createRequest->files->set('photo_0', UploadedFile::fake()->image('old-photo1.jpg'));
        $createRequest->files->set('photo_1', UploadedFile::fake()->image('old-photo2.png'));
        $createRequest->merge(['photos' => json_encode([[], []])]);

        $data = [
            'name' => 'Test Room',
            'capacity' => 2,
            'daily_rate' => 50.00,
            'semester_rate' => 1500.00,
        ];

        $roomType = $this->service->createRoomType($data, $createRequest);
        $oldPhotos = $roomType->photos;

        // Update with new photos
        $updateRequest = new Request();
        $updateRequest->files->set('photo_0', UploadedFile::fake()->image('new-photo1.jpg'));
        $updateRequest->files->set('photo_1', UploadedFile::fake()->image('new-photo2.png'));
        $updateRequest->merge(['photos' => json_encode([[], []])]);


        $updateData = ['name' => 'Updated Room'];

        $updatedRoomType = $this->service->updateRoomType($roomType, $updateData, $updateRequest);

        // Verify old photos are deleted
        Storage::disk('public')->assertMissing($oldPhotos[0]);
        Storage::disk('public')->assertMissing($oldPhotos[1]);

        // Verify new photos exist
        $this->assertNotNull($updatedRoomType->photos);
        $this->assertCount(2, $updatedRoomType->photos);
        Storage::disk('public')->assertExists($updatedRoomType->photos[0]);
        Storage::disk('public')->assertExists($updatedRoomType->photos[1]);
    }

    #[Test]
    public function it_handles_photo_upload_limit()
    {
        $request = new Request();
        $photosJson = [];
        // Add 12 photos (more than the limit of 10)
        for ($i = 0; $i < 12; $i++) {
            $request->files->set("photo_{$i}", UploadedFile::fake()->image("photo-{$i}.jpg"));
            $photosJson[] = [];
        }
        $request->merge(['photos' => json_encode($photosJson)]);

        $data = [
            'name' => 'Test Room',
            'capacity' => 2,
            'daily_rate' => 50.00,
            'semester_rate' => 1500.00,
        ];

        $roomType = $this->service->createRoomType($data, $request);

        // Should only have 10 photos due to limit in RoomTypeService
        $this->assertNotNull($roomType->photos);
        $this->assertCount(10, $roomType->photos);
    }

    #[Test]
    public function it_handles_empty_photo_array()
    {
        $request = new Request();
        $data = [
            'name' => 'Test Room',
            'capacity' => 2,
            'daily_rate' => 50.00,
            'semester_rate' => 1500.00,
        ];

        $roomType = $this->service->createRoomType($data, $request);

        $this->assertEmpty($roomType->photos);
    }
}
