<?php

namespace Tests\Unit;

use App\Services\FileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FileServiceTest extends TestCase
{
    use RefreshDatabase;

    private FileService $fileService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fileService = new FileService();
        Storage::fake('public');
    }

    /** @test */
    public function it_downloads_files_from_public_disk()
    {
        // Create a test file in public disk
        Storage::disk('public')->put('photos/test-photo.jpg', 'test photo content');

        // Test download through FileService
        $response = $this->fileService->downloadFile('photos/test-photo.jpg');

        // Should return a StreamedResponse
        $this->assertInstanceOf(\Symfony\Component\HttpFoundation\StreamedResponse::class, $response);
    }

    /** @test */
    public function it_returns_404_for_nonexistent_files()
    {
        // Try to download non-existent file
        $response = $this->fileService->downloadFile('photos/non-existent.jpg');

        // Should return JSON error response
        $this->assertInstanceOf(\Illuminate\Http\JsonResponse::class, $response);
        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals(['message' => 'File not found.'], json_decode($response->getContent(), true));
    }

    /** @test */
    public function it_validates_files_correctly()
    {
        // Test file validation
        $result = $this->fileService->validateFile('test-string');

        // String values should pass (existing file paths)
        $this->assertTrue($result['valid']);
        $this->assertNull($result['message']);
    }

    /** @test */
    public function it_handles_uploaded_file_validation()
    {
        // Test with UploadedFile
        $file = \Illuminate\Http\UploadedFile::fake()->image('test.jpg');
        $result = $this->fileService->validateFile($file);

        // Valid files should pass
        $this->assertTrue($result['valid']);
        $this->assertNull($result['message']);
    }

    /** @test */
    public function it_handles_different_file_types()
    {
        $testCases = [
            'photos/room.jpg',
            'avatars/student.png',
            'payment_checks/check.pdf',
            'documents/contract.doc'
        ];

        foreach ($testCases as $filePath) {
            // Create test file
            Storage::disk('public')->put($filePath, 'test content');

            // Test download
            $response = $this->fileService->downloadFile($filePath);
            $this->assertInstanceOf(\Symfony\Component\HttpFoundation\StreamedResponse::class, $response);
        }
    }

    /** @test */
    public function it_uploads_multiple_files_to_specific_folder()
    {
        $folder = 'room-type';
        $files = [
            UploadedFile::fake()->image('photo1.jpg'),
            UploadedFile::fake()->image('photo2.png'),
        ];

        $result = $this->fileService->uploadMultipleFiles($files, $folder);

        $this->assertCount(2, $result);
        $this->assertStringContainsString("room-type", $result[0]);
        $this->assertStringContainsString("room-type", $result[1]);

        // Verify files were stored
        Storage::disk('public')->assertExists($result[0]);
        Storage::disk('public')->assertExists($result[1]);
    }

    /** @test */
    public function it_limits_multiple_file_uploads()
    {
        $folder = 'room-type';
        $files = [
            UploadedFile::fake()->image('photo1.jpg'),
            UploadedFile::fake()->image('photo2.png'),
            UploadedFile::fake()->image('photo3.webp'),
        ];

        $result = $this->fileService->uploadMultipleFiles($files, $folder, 2);

        $this->assertCount(2, $result);
        $this->assertStringContainsString("room-type", $result[0]);
        $this->assertStringContainsString("room-type", $result[1]);
    }

    /** @test */
    public function it_handles_empty_file_array_upload()
    {
        $folder = 'room-type';
        $files = [];

        $result = $this->fileService->uploadMultipleFiles($files, $folder);

        $this->assertEmpty($result);
    }

    /** @test */
    public function it_deletes_multiple_files()
    {
        $folder = 'room-type';
        $files = [
            UploadedFile::fake()->image('photo1.jpg'),
            UploadedFile::fake()->image('photo2.png'),
        ];

        // Upload files first
        $uploadedPaths = $this->fileService->uploadMultipleFiles($files, $folder);

        // Verify files exist
        Storage::disk('public')->assertExists($uploadedPaths[0]);
        Storage::disk('public')->assertExists($uploadedPaths[1]);

        // Delete files
        $this->fileService->deleteMultipleFiles($uploadedPaths);

        // Verify files are deleted
        Storage::disk('public')->assertMissing($uploadedPaths[0]);
        Storage::disk('public')->assertMissing($uploadedPaths[1]);
    }

    /** @test */
    public function it_handles_deleting_empty_file_array()
    {
        $files = [];

        // Should not throw any exception
        $this->fileService->deleteMultipleFiles($files);

        $this->assertTrue(true); // Test passes if no exception is thrown
    }

    /** @test */
    public function it_handles_deleting_nonexistent_files()
    {
        $nonExistentFiles = [
            'room-type/nonexistent1.jpg',
            'room-type/nonexistent2.png',
        ];

        // Should not throw any exception
        $this->fileService->deleteMultipleFiles($nonExistentFiles);

        $this->assertTrue(true); // Test passes if no exception is thrown
    }

    /** @test */
    public function it_replaces_files_with_cleanup()
    {
        $folder = 'room-type';

        // Upload initial files
        $initialFiles = [
            UploadedFile::fake()->image('old_photo1.jpg'),
            UploadedFile::fake()->image('old_photo2.png'),
        ];
        $initialPaths = $this->fileService->uploadMultipleFiles($initialFiles, $folder);

        // Upload new files
        $newFiles = [
            UploadedFile::fake()->image('new_photo1.jpg'),
            UploadedFile::fake()->image('new_photo2.png'),
        ];

        $result = $this->fileService->replaceFilesWithCleanup($initialPaths, $newFiles, $folder);

        $this->assertCount(2, $result);
        $this->assertStringContainsString("room-type", $result[0]);
        $this->assertStringContainsString("room-type", $result[1]);

        // Verify old files are deleted
        Storage::disk('public')->assertMissing($initialPaths[0]);
        Storage::disk('public')->assertMissing($initialPaths[1]);

        // Verify new files exist
        Storage::disk('public')->assertExists($result[0]);
        Storage::disk('public')->assertExists($result[1]);
    }

    /** @test */
    public function it_replaces_files_with_empty_array()
    {
        $folder = 'room-type';

        // Upload initial files
        $initialFiles = [
            UploadedFile::fake()->image('old_photo1.jpg'),
        ];
        $initialPaths = $this->fileService->uploadMultipleFiles($initialFiles, $folder);

        // Replace with empty array
        $result = $this->fileService->replaceFilesWithCleanup($initialPaths, [], $folder);

        $this->assertEmpty($result);

        // Verify old files are deleted
        Storage::disk('public')->assertMissing($initialPaths[0]);
    }

    /** @test */
    public function it_validates_image_files()
    {
        $validImage = UploadedFile::fake()->image('valid.jpg');
        $invalidFile = UploadedFile::fake()->create('invalid.txt', 100);

        $validResult = $this->fileService->validateImageFile($validImage);
        $invalidResult = $this->fileService->validateImageFile($invalidFile);

        $this->assertTrue($validResult['valid']);
        $this->assertNull($validResult['message']);

        $this->assertFalse($invalidResult['valid']);
        $this->assertNotNull($invalidResult['message']);
    }

    /** @test */
    public function it_validates_image_files_with_size_limit()
    {
        $validImage = UploadedFile::fake()->image('valid.jpg')->size(1000); // 1KB
        $oversizedImage = UploadedFile::fake()->image('oversized.jpg')->size(6000); // 6KB

        $validResult = $this->fileService->validateImageFile($validImage, 2048); // 2MB limit
        $oversizedResult = $this->fileService->validateImageFile($oversizedImage, 2048); // 2MB limit

        $this->assertTrue($validResult['valid']);
        $this->assertNull($validResult['message']);

        $this->assertFalse($oversizedResult['valid']);
        $this->assertNotNull($oversizedResult['message']);
        $this->assertStringContainsString('size', $oversizedResult['message']);
    }
}
