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
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * @coversDefaultClass \App\Http\Controllers\StudentController
 */
class StudentIdentificationFieldsTest extends TestCase
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

        // Use the first bed created by RoomFactory (capacity 2 creates beds automatically)
        $this->bed = $this->room->beds()->first();
    }

    /** @covers \App\Http\Controllers\StudentController::store */
    public function test_student_registration_accepts_identification_type_and_number(): void
    {
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
            ],
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('student_profiles', [
            'identification_type'   => 'passport',
            'identification_number' => 'A123456789',
        ]);
    }

    /** @covers \App\Http\Controllers\StudentController::store */
    public function test_student_registration_requires_identification_type(): void
    {
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
                'identification_number'    => 'A123456789',
                'agree_to_dormitory_rules' => true,
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors([ 'student_profile.identification_type' ]);
    }

    /** @covers \App\Http\Controllers\StudentController::store */
    public function test_student_registration_requires_identification_number(): void
    {
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
                'agree_to_dormitory_rules' => true,
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors([ 'student_profile.identification_number' ]);
    }

    /** @covers \App\Http\Controllers\StudentController::store */
    public function test_student_registration_validates_identification_type_enum(): void
    {
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
                'identification_type'      => 'invalid_type',
                'identification_number'    => 'A123456789',
                'agree_to_dormitory_rules' => true,
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors([ 'student_profile.identification_type' ]);
    }

    /** @covers \App\Http\Controllers\StudentController::store */
    public function test_student_registration_accepts_all_valid_identification_types(): void
    {
        $validTypes = [ 'passport', 'national_id', 'drivers_license', 'other' ];

        foreach ($validTypes as $index => $type) {
            // Create a new room and bed for each test to avoid conflicts
            $room = Room::factory()->create([
                'dormitory_id'  => $this->dormitory->id,
                'room_type_id'  => $this->roomType->id,
                'number'        => '10' . ($index + 2),
                'occupant_type' => 'student',
            ]);
            $bed = $room->beds()->first();

            $response = $this->actingAs($this->admin)->postJson('/api/students', [
                'name'                  => "John Doe {$type}",
                'first_name'            => 'John',
                'last_name'             => 'Doe',
                'email'                 => "john{$type}@example.com",
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
                    'identification_type'      => $type,
                    'identification_number'    => 'A123456789',
                    'agree_to_dormitory_rules' => true,
                ],
            ]);

            $response->assertStatus(201);
            $this->assertDatabaseHas('student_profiles', [
                'identification_type' => $type,
            ]);
        }
    }

    /** @covers \App\Http\Controllers\StudentController::update */
    public function test_student_update_can_modify_identification_fields(): void
    {
        $student = User::factory()->create([
            'role_id'      => $this->studentRole->id,
            'dormitory_id' => $this->dormitory->id,
            'room_id'      => $this->room->id,
            'email'        => 'student@example.com',
        ]);

        \App\Models\StudentProfile::factory()->create([
            'user_id'               => $student->id,
            'identification_type'   => 'passport',
            'identification_number' => 'OLD123',
        ]);

        $response = $this->actingAs($this->admin)->putJson("/api/students/{$student->id}", [
            'email'           => 'student@example.com',
            'student_profile' => [
                'identification_type'   => 'national_id',
                'identification_number' => 'NEW456',
            ],
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('student_profiles', [
            'user_id'               => $student->id,
            'identification_type'   => 'national_id',
            'identification_number' => 'NEW456',
        ]);
    }
}
