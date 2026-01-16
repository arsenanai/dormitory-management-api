<?php

namespace Tests\Unit;

use App\Http\Controllers\FileController;
use App\Services\FileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FileControllerTest extends TestCase
{
    use RefreshDatabase;

    private FileController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $fileService = new FileService();
        $this->controller = new FileController($fileService);
        Storage::fake('public');
    }

    /** @test */
    /** @covers \App\Http\Controllers\FileController::download */
    public function it_downloads_photos_through_unified_endpoint()
    {
        // Create a test photo file
        Storage::disk('public')->put('photos/room-photo-1.jpg', 'test photo content');

        // Make request to unified download endpoint
        $response = $this->get("/api/files/download/photos/room-photo-1.jpg");

        // Assertions
        $response->assertStatus(200);
        $response->assertHeader('Content-Disposition', 'attachment; filename=room-photo-1.jpg');

        // For StreamedResponse, check that the file exists and has the correct content
        $this->assertTrue(Storage::disk('public')->exists('photos/room-photo-1.jpg'));
        $this->assertEquals('test photo content', Storage::disk('public')->get('photos/room-photo-1.jpg'));
    }

    /** @test */
    /** @covers \App\Http\Controllers\FileController::download */
    public function it_downloads_avatars_through_unified_endpoint()
    {
        // Create a test avatar file
        Storage::disk('public')->put('avatars/student-avatar.png', 'test avatar content');

        // Make request to unified download endpoint
        $response = $this->get("/api/files/download/avatars/student-avatar.png");

        // Assertions
        $response->assertStatus(200);
        $response->assertHeader('Content-Disposition', 'attachment; filename=student-avatar.png');

        // For StreamedResponse, check that the file exists and has the correct content
        $this->assertTrue(Storage::disk('public')->exists('avatars/student-avatar.png'));
        $this->assertEquals('test avatar content', Storage::disk('public')->get('avatars/student-avatar.png'));
    }

    /** @test */
    /** @covers \App\Http\Controllers\FileController::download */
    public function it_downloads_payment_checks_through_unified_endpoint()
    {
        // Create a test payment check file
        Storage::disk('public')->put('payment_checks/check-123.jpg', 'test check content');

        // Make request to unified download endpoint
        $response = $this->get("/api/files/download/payment_checks/check-123.jpg");

        // Assertions
        $response->assertStatus(200);
        $response->assertHeader('Content-Disposition', 'attachment; filename=check-123.jpg');

        // For StreamedResponse, check that the file exists and has the correct content
        $this->assertTrue(Storage::disk('public')->exists('payment_checks/check-123.jpg'));
        $this->assertEquals('test check content', Storage::disk('public')->get('payment_checks/check-123.jpg'));
    }

    /** @test */
    /** @covers \App\Http\Controllers\FileController::download */
    public function it_returns_404_for_nonexistent_files()
    {
        // Make request to unified download endpoint for non-existent file
        $response = $this->get("/api/files/download/photos/non-existent.jpg");

        // Assertions
        $response->assertStatus(404);
        $response->assertJson(['message' => 'File not found.']);
    }

    /** @test */
    /** @covers \App\Http\Controllers\FileController::download */
    public function it_handles_nested_directory_paths()
    {
        // Create a test file in nested directory
        Storage::disk('public')->put('student/files/identification/passport.pdf', 'test passport content');

        // Make request to unified download endpoint with nested path
        $response = $this->get("/api/files/download/student/files/identification/passport.pdf");

        // Assertions
        $response->assertStatus(200);
        $response->assertHeader('Content-Disposition', 'attachment; filename=passport.pdf');

        // For StreamedResponse, check that the file exists and has the correct content
        $this->assertTrue(Storage::disk('public')->exists('student/files/identification/passport.pdf'));
        $this->assertEquals('test passport content', Storage::disk('public')->get('student/files/identification/passport.pdf'));
    }
}
