<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Bed;
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
 * @coversDefaultClass \App\Http\Controllers\StudentController
 */
class StudentPhotoUploadTest extends TestCase
{
    use RefreshDatabase;

    private Role $adminRole;
    private Role $studentRole;
    private Dormitory $dormitory;
    private RoomType $roomType;
    private Room $room;
    private Bed $bed;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->adminRole = Role::factory()->create([ 'name' => 'admin' ]);
        $this->studentRole = Role::factory()->create([ 'name' => 'student' ]);

        $this->admin = User::factory()->create([ 'role_id' => $this->adminRole->id ]);

        $this->dormitory = Dormitory::factory()->create([
            'admin_id' => $this->admin->id,
            'gender'   => 'male',
        ]);

        $this->roomType = RoomType::factory()->create([
            'name'     => 'Standard',
            'capacity' => 2,
        ]);

        $this->room = Room::factory()->create([
            'dormitory_id'  => $this->dormitory->id,
            'room_type_id'  => $this->roomType->id,
            'number'        => '101',
            'occupant_type' => 'student',
        ]);

        // Use the first bed created by RoomFactory
        $this->bed = $this->room->beds()->first();
    }

    /** @covers \App\Http\Controllers\StudentController::store */
    public function test_student_registration_accepts_photo_upload(): void
    {
        $photo = UploadedFile::fake()->image('student-photo.jpg', 300, 400);

        $response = $this->actingAs($this->admin)->postJson('/api/students', [
            'name'                  => 'John Doe',
            'first_name'            => 'John',
            'last_name'             => 'Doe',
            'email'                 => 'john@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
            'phone_numbers'         => [ '+77001234567' ],
            'dormitory_id'          => $this->dormitory->id,
            'bed_id'                => $this->bed->id,
            'student_profile'       => [
                'iin'                      => '123456789012',
                'faculty'                  => 'Engineering',
                'specialist'               => 'Computer Science',
                'enrollment_year'          => 2024,
                'gender'                   => 'male',
                'identification_type'      => 'passport',
                'identification_number'    => 'A123456789',
                'agree_to_dormitory_rules' => true,
                'files'                    => [
                    null, // files[0] - document 1
                    null, // files[1] - document 2
                    $photo, // files[2] - student photo (3x4)
                ],
            ],
        ]);

        $response->assertStatus(201);

        $studentProfile = \App\Models\StudentProfile::whereHas('user', function ($q) {
            $q->where('email', 'john@example.com');
        })->first();

        $this->assertNotNull($studentProfile);
        $rawFiles = $studentProfile->files;
        $files = is_string($rawFiles) ? json_decode($rawFiles, true) : (is_array($rawFiles) ? $rawFiles : []);
        $this->assertIsArray($files);
        $this->assertNotNull($files[2]); // Photo should be stored at index 2
        $this->assertStringContainsString('avatars', $files[2]);

        // Verify file was stored
        Storage::disk('public')->assertExists($files[2]);
    }

    /** @covers \App\Http\Controllers\StudentController::store */
    public function test_student_registration_validates_photo_file_type(): void
    {
        $pdfFile = UploadedFile::fake()->create('document.pdf', 100);

        $response = $this->actingAs($this->admin)->postJson('/api/students', [
            'name'                  => 'John Doe',
            'first_name'            => 'John',
            'last_name'             => 'Doe',
            'email'                 => 'john@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
            'phone_numbers'         => [ '+77001234567' ],
            'dormitory_id'          => $this->dormitory->id,
            'bed_id'                => $this->bed->id,
            'student_profile'       => [
                'iin'                      => '123456789012',
                'faculty'                  => 'Engineering',
                'specialist'               => 'Computer Science',
                'enrollment_year'          => 2024,
                'gender'                   => 'male',
                'identification_type'      => 'passport',
                'identification_number'    => 'A123456789',
                'agree_to_dormitory_rules' => true,
                'files'                    => [
                    null,
                    null,
                    $pdfFile, // PDF should not be accepted for photo
                ],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors([ 'student_profile.files.2' ]);
    }

    /** @covers \App\Http\Controllers\StudentController::store */
    public function test_student_registration_validates_photo_file_size(): void
    {
        // Create an image larger than 2MB
        $largePhoto = UploadedFile::fake()->image('large-photo.jpg', 300, 400)->size(3000); // 3MB

        $response = $this->actingAs($this->admin)->postJson('/api/students', [
            'name'                  => 'John Doe',
            'first_name'            => 'John',
            'last_name'             => 'Doe',
            'email'                 => 'john@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
            'phone_numbers'         => [ '+77001234567' ],
            'dormitory_id'          => $this->dormitory->id,
            'bed_id'                => $this->bed->id,
            'student_profile'       => [
                'iin'                      => '123456789012',
                'faculty'                  => 'Engineering',
                'specialist'               => 'Computer Science',
                'enrollment_year'          => 2024,
                'gender'                   => 'male',
                'identification_type'      => 'passport',
                'identification_number'    => 'A123456789',
                'agree_to_dormitory_rules' => true,
                'files'                    => [
                    null,
                    null,
                    $largePhoto,
                ],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors([ 'student_profile.files.2' ]);
    }

    /** @covers \App\Http\Controllers\StudentController::update */
    public function test_student_update_can_upload_photo(): void
    {
        $student = User::factory()->create([
            'role_id'      => $this->studentRole->id,
            'dormitory_id' => $this->dormitory->id,
            'room_id'      => $this->room->id,
            'email'        => 'student@example.com',
        ]);

        \App\Models\StudentProfile::factory()->create([
            'user_id' => $student->id,
            'files'   => json_encode([ null, null, null ]),
        ]);

        $photo = UploadedFile::fake()->image('new-photo.jpg', 300, 400);

        $response = $this->actingAs($this->admin)->postJson("/api/students/{$student->id}/photo", [
            'photo' => $photo,
        ]);

        // Check if endpoint exists, if not, test via update endpoint
        if ($response->status() === 404) {
            $response = $this->actingAs($this->admin)->putJson("/api/students/{$student->id}", [
                'email'           => 'student@example.com',
                'student_profile' => [
                    'files' => [
                        null,
                        null,
                        $photo,
                    ],
                ],
            ]);
        }

        $response->assertStatus(200);

        $studentProfile = $student->fresh()->studentProfile;
        $this->assertNotNull($studentProfile);
        $rawFiles = $studentProfile->files;
        $files = is_string($rawFiles) ? json_decode($rawFiles, true) : (is_array($rawFiles) ? $rawFiles : []);
        $this->assertNotNull($files[2] ?? null);
        Storage::disk('public')->assertExists($files[2]);
    }

    /** @covers \App\Http\Controllers\StudentController::store */
    public function test_student_registration_accepts_valid_image_formats(): void
    {
        $formats = [
            [ 'jpg', 'image/jpeg' ],
            [ 'jpeg', 'image/jpeg' ],
            [ 'png', 'image/png' ],
        ];

        foreach ($formats as $index => [ $ext, $mime ]) {
            $room = Room::factory()->create([
                'dormitory_id'  => $this->dormitory->id,
                'room_type_id'  => $this->roomType->id,
                'number'        => '20' . ($index + 1),
                'occupant_type' => 'student',
            ]);
            $bed = $room->beds()->first();

            $photo = UploadedFile::fake()->image("photo.{$ext}", 300, 400);

            $response = $this->actingAs($this->admin)->postJson('/api/students', [
                'name'                  => "John Doe {$ext}",
                'first_name'            => 'John',
                'last_name'             => 'Doe',
                'email'                 => "john{$ext}@example.com",
                'password'              => 'password123',
                'password_confirmation' => 'password123',
                'phone_numbers'         => [ '+77001234567' ],
                'dormitory_id'          => $this->dormitory->id,
                'bed_id'                => $bed->id,
                'student_profile'       => [
                    'iin'                      => '1234567890' . str_pad((string) $index, 2, '0', STR_PAD_LEFT),
                    'faculty'                  => 'Engineering',
                    'specialist'               => 'Computer Science',
                    'enrollment_year'          => 2024,
                    'gender'                   => 'male',
                    'identification_type'      => 'passport',
                    'identification_number'    => 'A123456789',
                    'agree_to_dormitory_rules' => true,
                    'files'                    => [
                        null,
                        null,
                        $photo,
                    ],
                ],
            ]);

            $response->assertStatus(201);

            $studentProfile = \App\Models\StudentProfile::whereHas('user', function ($q) use ($ext) {
                $q->where('email', "john{$ext}@example.com");
            })->first();

            $this->assertNotNull($studentProfile);
            $rawFiles = $studentProfile->files;
            $files = is_string($rawFiles) ? json_decode($rawFiles, true) : (is_array($rawFiles) ? $rawFiles : []);
            $this->assertNotNull($files[2] ?? null);
        }
    }
}
