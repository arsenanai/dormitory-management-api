<?php

namespace Tests\Unit\Services;

use App\Services\FileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * @coversDefaultClass \App\Services\FileService
 */
class FileServiceTest extends TestCase
{
    use RefreshDatabase;

    private FileService $fileService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fileService = new FileService();
        Storage::fake('local');
    }

    /**
     * Test file validation with valid uploaded file
     */
    public function test_validate_file_valid_uploaded_file(): void
    {
        $file = UploadedFile::fake()->create('document.pdf', 1000, 'application/pdf');
        $result = $this->fileService->validateFile($file);

        $this->assertTrue($result['valid']);
        $this->assertNull($result['message']);
    }

    /**
     * Test file validation with invalid file type
     */
    public function test_validate_file_invalid_file_type(): void
    {
        $file = UploadedFile::fake()->create('video.mp4', 5000, 'video/mp4');
        $result = $this->fileService->validateFile($file);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('file of type', $result['message']);
    }

    /**
     * Test file validation with file too large
     */
    public function test_validate_file_too_large(): void
    {
        $file = UploadedFile::fake()->create('document.pdf', 3000, 'application/pdf');
        $result = $this->fileService->validateFile($file);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('not be greater than 2048', $result['message']);
    }

    /**
     * Test file validation with string value (existing file path)
     */
    public function test_validate_file_string_value(): void
    {
        $filePath = 'student_files/existing_document.pdf';
        $result = $this->fileService->validateFile($filePath);

        $this->assertTrue($result['valid']);
        $this->assertNull($result['message']);
    }

    /**
     * Test file validation with null value
     */
    public function test_validate_file_null_value(): void
    {
        $result = $this->fileService->validateFile(null);

        $this->assertTrue($result['valid']);
        $this->assertNull($result['message']);
    }

    /**
     * Test file validation with custom rules
     */
    public function test_validate_file_with_custom_rules(): void
    {
        $file = UploadedFile::fake()->create('image.png', 500, 'image/png');
        $customRules = [ 'mimes:png,jpg', 'max:1000' ];
        $result = $this->fileService->validateFile($file, $customRules);

        $this->assertTrue($result['valid']);
        $this->assertNull($result['message']);
    }

    /**
     * Test download student file with valid image file
     */
    public function test_download_student_file_valid_image(): void
    {
        $filename = 'test_avatar.jpg';
        $path = "student_files/{$filename}";

        // Create a fake file
        Storage::disk('local')->put($path, 'fake image content');

        $response = $this->fileService->downloadStudentFile($filename);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('attachment; filename=test_avatar.jpg', $response->headers->get('Content-Disposition'));
    }

    /**
     * Test download student file with non-existent file
     */
    public function test_download_student_file_non_existent(): void
    {
        $filename = 'non_existent.jpg';

        $response = $this->fileService->downloadStudentFile($filename);

        $this->assertEquals(404, $response->getStatusCode());
        $jsonData = $response->getData(true);
        $this->assertEquals('File not found.', $jsonData['message']);
    }

    /**
     * Test download student file with unauthorized file type
     */
    public function test_download_student_file_unauthorized_type(): void
    {
        $filename = 'document.pdf';
        $path = "student_files/{$filename}";

        // Create a fake PDF file
        Storage::disk('local')->put($path, 'fake pdf content');

        $response = $this->fileService->downloadStudentFile($filename);

        $this->assertEquals(403, $response->getStatusCode());
        $jsonData = $response->getData(true);
        $this->assertEquals('Unauthorized file type.', $jsonData['message']);
    }

    /**
     * Test download student file with different image extensions
     */
    public function test_download_student_file_different_image_extensions(): void
    {
        $extensions = [ 'jpg', 'jpeg', 'png', 'gif', 'webp' ];

        foreach ($extensions as $ext) {
            $filename = "test_image.{$ext}";
            $path = "student_files/{$filename}";

            Storage::disk('local')->put($path, "fake {$ext} content");

            $response = $this->fileService->downloadStudentFile($filename);

            $this->assertEquals(200, $response->getStatusCode(), "Failed for extension: {$ext}");
        }
    }

    /**
     * Test download authenticated file with valid file
     */
    public function test_download_authenticated_file_valid(): void
    {
        $path = 'student_files/test_document.pdf';

        // Create a fake file
        Storage::disk('local')->put($path, 'fake document content');

        $response = $this->fileService->downloadAuthenticatedFile($path);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('attachment; filename=test_document.pdf', $response->headers->get('Content-Disposition'));
    }

    /**
     * Test download authenticated file with non-existent file
     */
    public function test_download_authenticated_file_non_existent(): void
    {
        $path = 'student_files/non_existent.pdf';

        $response = $this->fileService->downloadAuthenticatedFile($path);

        $this->assertEquals(404, $response->getStatusCode());
        $jsonData = $response->getData(true);
        $this->assertEquals('File not found.', $jsonData['message']);
    }

    /**
     * Test log file info method
     */
    public function test_log_file_info(): void
    {
        $file = UploadedFile::fake()->create('test.pdf', 1000, 'application/pdf');

        // This should not throw an exception
        $this->expectNotToPerformAssertions();
        $this->fileService->logFileInfo(0, $file);
    }
}
