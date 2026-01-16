<?php

namespace Tests\Unit;

use App\Http\Controllers\RoomTypeController;
use App\Services\RoomTypeService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Uses;
use Tests\TestCase;

#[CoversClass(RoomTypeController::class)]
#[Uses(RoomTypeService::class)]
class RoomTypeValidationTest extends TestCase
{
    private RoomTypeController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        // Mock the service dependency, we are only testing the controller validation rules
        $mockService = $this->createMock(\App\Services\RoomTypeService::class);
        $this->controller = new RoomTypeController($mockService);
    }

    #[Test]
    public function it_validates_photos_field_and_dynamic_photo_uploads()
    {
        // Simulate a request with a 'photos' JSON string and a few uploaded photo files
        $request = new Request([
            'name' => 'Test Room',
            'capacity' => 2,
            'photos' => json_encode(['path/to/existing.jpg', [], []]), // 1 existing, 2 new
        ]);

        // Add some fake uploaded files to the request
        $request->files->set('photo_1', UploadedFile::fake()->image('new1.jpg'));
        $request->files->set('photo_2', UploadedFile::fake()->image('new2.png'));

        // Get the validation rules from the controller
        $rules = $this->controller->getRulesForUpdate($request);

        // Assert that the 'photos' rule is a string
        $this->assertEquals('sometimes|string', $rules['photos']);

        // Assert that the rules for the uploaded photos are correct
        $this->assertEquals('sometimes|image|mimes:jpg,jpeg,png,webp|max:5120', $rules['photo_1']);
        $this->assertEquals('sometimes|image|mimes:jpg,jpeg,png,webp|max:5120', $rules['photo_2']);

        // Assert that a rule for a non-uploaded photo index is also present
        $this->assertArrayHasKey('photo_0', $rules);
        $this->assertEquals('sometimes|image|mimes:jpg,jpeg,png,webp|max:5120', $rules['photo_0']);

        // Assert that a rule for an out-of-bounds photo index is also present (up to 9)
        $this->assertArrayHasKey('photo_9', $rules);
        $this->assertEquals('sometimes|image|mimes:jpg,jpeg,png,webp|max:5120', $rules['photo_9']);
    }

    #[Test]
    public function it_has_correct_base_rules_when_no_photos_are_provided()
    {
        // Simulate a request without any photo fields
        $request = new Request([
            'name' => 'Test Room',
            'capacity' => 2,
        ]);

        // Get the validation rules from the controller
        $rules = $this->controller->getRulesForUpdate($request);

        // The 'photos' rule should still be present
        $this->assertEquals('sometimes|string', $rules['photos']);

        // The dynamic photo rules should also be present
        $this->assertArrayHasKey('photo_0', $rules);
        $this->assertEquals('sometimes|image|mimes:jpg,jpeg,png,webp|max:5120', $rules['photo_0']);
        $this->assertArrayHasKey('photo_5', $rules);
        $this->assertEquals('sometimes|image|mimes:jpg,jpeg,png,webp|max:5120', $rules['photo_5']);
    }
}
