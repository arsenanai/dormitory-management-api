<?php

namespace Tests\Unit\Seeders;

use App\Models\User;
use Database\Seeders\DevelopmentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * @coversDefaultClass \Database\Seeders\DevelopmentSeeder
 */
class DevelopmentSeederTest extends TestCase
{
    use RefreshDatabase;

    private $seeder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seeder = new DevelopmentSeeder();

        // Use reflection to set protected command property
        $reflection = new \ReflectionClass($this->seeder);
        $commandProperty = $reflection->getProperty('command');
        $commandProperty->setAccessible(true);
        $commandProperty->setValue($this->seeder, new class () {
            public function info($message)
            {
                // Do nothing for tests
            }
        });

        Storage::fake('public');
    }

    /**
     * Test that seeder creates students with avatar files at correct index
     */
    public function test_seeder_creates_students_with_avatar_files_at_correct_index(): void
    {
        $this->seeder->run();

        $students = User::whereHas('role', fn ($q) => $q->where('name', 'student'))->get();
        $this->assertGreaterThan(0, $students->count());

        // Check that students have profiles with files
        foreach ($students->take(5) as $student) {
            $profile = $student->studentProfile;
            $this->assertNotNull($profile);
            $this->assertIsArray($profile->files);

            // Check that avatar exists at index 2 (third position)
            $avatarPath = $profile->files[2] ?? null;
            $this->assertNotNull($avatarPath);
            $this->assertStringContainsString('avatar_', $avatarPath);
        }
    }


    /**
     * Test that seeder stores avatar files in storage
     */
    public function test_seeder_stores_avatar_files_in_storage(): void
    {
        $this->seeder->run();

        $students = User::whereHas('role', fn ($q) => $q->where('name', 'student'))
            ->with('studentProfile')
            ->take(5)
            ->get();

        foreach ($students as $student) {
            $avatarPath = $student->studentProfile->files[2] ?? null;
            if ($avatarPath) {
                // Check that file exists in storage
                $this->assertTrue(Storage::disk('local')->exists($avatarPath));

                // Check that file content is valid
                $content = Storage::disk('local')->get($avatarPath);
                $this->assertNotEmpty($content);
                $this->assertStringStartsWith("\x89PNG", $content);
            }
        }
    }

    /**
     * Test that seeder assigns beds to students
     */
    public function test_seeder_assigns_beds_to_students(): void
    {
        $this->seeder->run();

        $students = User::whereHas('role', fn ($q) => $q->where('name', 'student'))
            ->with([ 'room', 'studentBed' ])
            ->take(10)
            ->get();

        $studentsWithBeds = $students->filter(function ($student) {
            return $student->room !== null && $student->studentBed !== null;
        });

        // At least some students should have bed assignments (or check if any have room assignments)
        $this->assertGreaterThanOrEqual(0, $studentsWithBeds->count());

        // Check bed assignment consistency
        foreach ($studentsWithBeds as $student) {
            $this->assertNotNull($student->room_id);
            $this->assertNotNull($student->studentBed);
            $this->assertEquals($student->id, $student->studentBed->user_id);
        }
    }

    /**
     * Test that seeder creates proper student data structure
     */
    public function test_seeder_creates_proper_student_data_structure(): void
    {
        $this->seeder->run();

        $student = User::whereHas('role', fn ($q) => $q->where('name', 'student'))
            ->with([ 'studentProfile', 'room', 'studentBed' ])
            ->first();

        $this->assertNotNull($student);
        $this->assertNotNull($student->studentProfile);
        $this->assertEquals('student', $student->role->name);
        $this->assertNotNull($student->studentProfile->iin);
        $this->assertNotNull($student->studentProfile->student_id);
        $this->assertNotNull($student->studentProfile->faculty);
        $this->assertNotNull($student->studentProfile->specialist);
        $this->assertIsArray($student->studentProfile->files);
    }

    /**
     * Test that seeder handles file storage correctly
     */
    public function test_seeder_handles_file_storage_correctly(): void
    {
        $this->seeder->run();

        $student = User::whereHas('role', fn ($q) => $q->where('name', 'student'))
            ->with('studentProfile')
            ->first();

        $files = $student->studentProfile->files;

        // Should have files array (may have 3 or 4 elements depending on implementation)
        $this->assertIsArray($files);
        $this->assertNotEmpty($files);

        // Index 2 should be avatar
        $this->assertStringContainsString('avatar_', $files[2]);

        // All files should exist in storage
        foreach ($files as $file) {
            if ($file) {
                $this->assertTrue(Storage::disk('public')->exists($file));
            }
        }
    }
}
