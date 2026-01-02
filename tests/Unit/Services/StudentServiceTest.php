<?php

namespace Tests\Unit\Services;

use App\Services\StudentService;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class StudentServiceTest extends TestCase
{
    private StudentService $studentService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->studentService = new StudentService();
    }

    /**
     * Test file validation with valid uploaded file at index 0
     */
    public function test_validate_student_file_valid_uploaded_file_index_0(): void
    {
        $file = UploadedFile::fake()->create('document.pdf', 1000, 'application/pdf');
        $result = $this->studentService->validateStudentFile($file, 0);

        $this->assertTrue($result['valid']);
        $this->assertNull($result['message']);
    }

    /**
     * Test file validation with valid uploaded file at index 2 (avatar)
     */
    public function test_validate_student_file_valid_uploaded_file_index_2(): void
    {
        $file = UploadedFile::fake()->image('avatar.jpg', 200, 300);
        $result = $this->studentService->validateStudentFile($file, 2);

        $this->assertTrue($result['valid']);
        $this->assertNull($result['message']);
    }

    /**
     * Test file validation with invalid file type at index 0
     */
    public function test_validate_student_file_invalid_file_type_index_0(): void
    {
        $file = UploadedFile::fake()->create('video.mp4', 5000, 'video/mp4');
        $result = $this->studentService->validateStudentFile($file, 0);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('file of type', $result['message']);
    }

    /**
     * Test file validation with file too large at index 2
     */
    public function test_validate_student_file_file_too_large_index_2(): void
    {
        $file = UploadedFile::fake()->image('avatar.jpg', 200, 300)->size(2048);
        $result = $this->studentService->validateStudentFile($file, 2);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('not be greater than 1024', $result['message']);
    }

    /**
     * Test file validation with avatar dimensions too small
     */
    public function test_validate_student_file_avatar_dimensions_too_small(): void
    {
        $file = UploadedFile::fake()->image('avatar.jpg', 100, 100);
        $result = $this->studentService->validateStudentFile($file, 2);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('dimensions', $result['message']);
    }

    /**
     * Test file validation with string value (existing file path)
     */
    public function test_validate_student_file_string_value(): void
    {
        $filePath = 'student_files/existing_document.pdf';
        $result = $this->studentService->validateStudentFile($filePath, 0);

        $this->assertTrue($result['valid']);
        $this->assertNull($result['message']);
    }

    /**
     * Test file validation with null value
     */
    public function test_validate_student_file_null_value(): void
    {
        $result = $this->studentService->validateStudentFile(null, 0);

        $this->assertTrue($result['valid']);
        $this->assertNull($result['message']);
    }

    /**
     * Test construct full name with both first and last name
     */
    public function test_construct_full_name_with_both_names(): void
    {
        $data = [
            'first_name' => 'John',
            'last_name' => 'Doe'
        ];

        $result = $this->studentService->constructFullName($data);

        $this->assertEquals('John Doe', $result);
    }

    /**
     * Test construct full name with extra spaces
     */
    public function test_construct_full_name_with_extra_spaces(): void
    {
        $data = [
            'first_name' => '  John  ',
            'last_name' => '  Doe  '
        ];

        $result = $this->studentService->constructFullName($data);

        $this->assertEquals('John Doe', $result);
    }

    /**
     * Test construct full name with only first name
     */
    public function test_construct_full_name_with_only_first_name(): void
    {
        $data = [
            'first_name' => 'John',
            'last_name' => ''
        ];

        $result = $this->studentService->constructFullName($data);

        $this->assertEquals('John', $result);
    }

    /**
     * Test construct full name with only last name
     */
    public function test_construct_full_name_with_only_last_name(): void
    {
        $data = [
            'first_name' => '',
            'last_name' => 'Doe'
        ];

        $result = $this->studentService->constructFullName($data);

        $this->assertEquals('Doe', $result);
    }

    /**
     * Test construct full name with missing names
     */
    public function test_construct_full_name_with_missing_names(): void
    {
        $data = [];

        $result = $this->studentService->constructFullName($data);

        $this->assertEquals('', $result);
    }

    /**
     * Test log file info method
     */
    public function test_log_file_info(): void
    {
        $file = UploadedFile::fake()->create('test.pdf', 1000, 'application/pdf');

        // This should not throw an exception
        $this->expectNotToPerformAssertions();
        $this->studentService->logFileInfo(0, $file);
    }
}
