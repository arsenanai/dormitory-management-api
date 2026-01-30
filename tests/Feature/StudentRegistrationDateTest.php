<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Bed;
use App\Models\Dormitory;
use App\Models\Role;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * @coversDefaultClass \App\Http\Controllers\StudentController
 */
class StudentRegistrationDateTest extends TestCase
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

        $this->bed = $this->room->beds()->first();
    }

    /** @covers \App\Http\Controllers\StudentController::index */
    public function test_students_index_api_returns_created_at_as_registration_date(): void
    {
        $registrationDate = Carbon::now()->subDays(5);

        $student = User::factory()->create([
            'role_id'      => $this->studentRole->id,
            'dormitory_id' => $this->dormitory->id,
            'room_id'      => $this->room->id,
            'created_at'   => $registrationDate,
        ]);

        \App\Models\StudentProfile::factory()->create([
            'user_id' => $student->id,
        ]);

        $response = $this->actingAs($this->admin)->getJson('/api/students');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'email',
                    'created_at',
                ],
            ],
        ]);

        $studentData = collect($response->json('data'))->firstWhere('id', $student->id);
        $this->assertNotNull($studentData);
        $this->assertArrayHasKey('created_at', $studentData);
        $this->assertNotNull($studentData['created_at']);
    }

    /** @covers \App\Http\Controllers\StudentController::export */
    public function test_students_csv_export_includes_registration_date(): void
    {
        $registrationDate = Carbon::now()->subDays(10);

        $student = User::factory()->create([
            'role_id'      => $this->studentRole->id,
            'dormitory_id' => $this->dormitory->id,
            'room_id'      => $this->room->id,
            'created_at'   => $registrationDate,
        ]);

        \App\Models\StudentProfile::factory()->create([
            'user_id'         => $student->id,
            'iin'             => '123456789012',
            'faculty'         => 'Engineering',
            'enrollment_year' => 2024,
        ]);

        $response = $this->actingAs($this->admin)->getJson('/api/students/export?columns=name,status,created_at');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=utf-8');

        $csvContent = $response->getContent();
        $this->assertStringContainsString('Registered date', $csvContent);
        $this->assertStringContainsString($registrationDate->format('Y-m-d H:i:s'), $csvContent);
    }

    /** @covers \App\Http\Controllers\StudentController::export */
    public function test_students_csv_export_formats_registration_date_correctly(): void
    {
        $registrationDate = Carbon::parse('2024-01-15 14:30:00');

        $student = User::factory()->create([
            'role_id'      => $this->studentRole->id,
            'dormitory_id' => $this->dormitory->id,
            'room_id'      => $this->room->id,
            'created_at'   => $registrationDate,
        ]);

        \App\Models\StudentProfile::factory()->create([
            'user_id'         => $student->id,
            'iin'             => '123456789012',
            'faculty'         => 'Engineering',
            'enrollment_year' => 2024,
        ]);

        $response = $this->actingAs($this->admin)->getJson('/api/students/export?columns=name,created_at');

        $response->assertStatus(200);
        $csvContent = $response->getContent();

        // Check that the date is formatted as Y-m-d H:i:s
        $expectedDate = '2024-01-15 14:30:00';
        $this->assertStringContainsString($expectedDate, $csvContent);
    }

    /** @covers \App\Http\Controllers\StudentController::index */
    public function test_students_index_shows_registration_date_in_correct_format(): void
    {
        $registrationDate = Carbon::parse('2024-03-20 10:15:30');

        $student = User::factory()->create([
            'role_id'      => $this->studentRole->id,
            'dormitory_id' => $this->dormitory->id,
            'room_id'      => $this->room->id,
            'created_at'   => $registrationDate,
        ]);

        \App\Models\StudentProfile::factory()->create([
            'user_id' => $student->id,
        ]);

        $response = $this->actingAs($this->admin)->getJson('/api/students');

        $response->assertStatus(200);
        $studentData = collect($response->json('data'))->firstWhere('id', $student->id);

        $this->assertNotNull($studentData['created_at']);
        // The API should return ISO 8601 format for dates
        $this->assertStringContainsString('2024-03-20', $studentData['created_at']);
    }

    /** @covers \App\Http\Controllers\StudentController::index */
    public function test_students_with_different_registration_dates_are_ordered_correctly(): void
    {
        $oldestDate = Carbon::now()->subDays(30);
        $middleDate = Carbon::now()->subDays(15);
        $newestDate = Carbon::now()->subDays(5);

        $oldestStudent = User::factory()->create([
            'role_id'      => $this->studentRole->id,
            'dormitory_id' => $this->dormitory->id,
            'room_id'      => $this->room->id,
            'created_at'   => $oldestDate,
        ]);

        $middleStudent = User::factory()->create([
            'role_id'      => $this->studentRole->id,
            'dormitory_id' => $this->dormitory->id,
            'room_id'      => $this->room->id,
            'created_at'   => $middleDate,
        ]);

        $newestStudent = User::factory()->create([
            'role_id'      => $this->studentRole->id,
            'dormitory_id' => $this->dormitory->id,
            'room_id'      => $this->room->id,
            'created_at'   => $newestDate,
        ]);

        \App\Models\StudentProfile::factory()->create([ 'user_id' => $oldestStudent->id ]);
        \App\Models\StudentProfile::factory()->create([ 'user_id' => $middleStudent->id ]);
        \App\Models\StudentProfile::factory()->create([ 'user_id' => $newestStudent->id ]);

        $response = $this->actingAs($this->admin)->getJson('/api/students');

        $response->assertStatus(200);
        $students = collect($response->json('data'));

        $oldestData = $students->firstWhere('id', $oldestStudent->id);
        $middleData = $students->firstWhere('id', $middleStudent->id);
        $newestData = $students->firstWhere('id', $newestStudent->id);

        $this->assertNotNull($oldestData);
        $this->assertNotNull($middleData);
        $this->assertNotNull($newestData);

        // Verify dates are present and correct
        $this->assertStringContainsString($oldestDate->format('Y-m-d'), $oldestData['created_at']);
        $this->assertStringContainsString($middleDate->format('Y-m-d'), $middleData['created_at']);
        $this->assertStringContainsString($newestDate->format('Y-m-d'), $newestData['created_at']);
    }
}
