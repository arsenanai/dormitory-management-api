<?php

namespace Tests\Feature;

use App\Models\Bed;
use App\Models\Dormitory;
use App\Models\Role;
use App\Models\Room;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StudentControllerTest extends TestCase
{
    use RefreshDatabase;
    use WithFaker;

    private User $adminUser;
    private User $sudoUser;
    private User $studentUser;
    private Dormitory $testDormitory;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->seedTestData();
    }

    private function seedTestData(): void
    {
        // Create roles
        $studentRole = Role::factory()->create(['name' => 'student']);
        $adminRole = Role::factory()->create(['name' => 'admin']);
        $sudoRole = Role::factory()->create(['name' => 'sudo']);

        // Create dormitory
        $this->testDormitory = Dormitory::factory()->create([
            'name' => 'Test Dormitory',
            'gender' => 'male',
            'admin_id' => null  // Will be set below
        ]);

        // Create users with tokens
        $this->adminUser = User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'role_id' => $adminRole->id,
            'dormitory_id' => $this->testDormitory->id
        ]);

        // Set up adminDormitory relationship
        $this->testDormitory->update(['admin_id' => $this->adminUser->id]);

        Sanctum::actingAs($this->adminUser);

        $this->sudoUser = User::factory()->create([
            'name' => 'Sudo User',
            'email' => 'sudo@test.com',
            'role_id' => $sudoRole->id
        ]);

        $this->studentUser = User::factory()->create([
            'name' => 'Student User',
            'email' => 'student@test.com',
            'role_id' => $studentRole->id
        ]);
    }

    // ========== Index Tests ==========

    /**
     * Test admin can list students
     */
    public function test_admin_can_list_students(): void
    {
        $student = $this->createTestStudent();

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/students');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'email',
                        'status',
                        'student_profile',
                        'role',
                        'room'
                    ]
                ],
                'current_page',
                'last_page',
                'per_page',
                'total'
            ]);

        $this->assertEquals(1, $response->json('total'));
    }

    /**
     * Test admin can filter students by faculty
     */
    public function test_admin_can_filter_students_by_faculty(): void
    {
        $student1 = $this->createTestStudent();
        $student2 = $this->createTestStudent();

        $student1->studentProfile->update(['faculty' => 'engineering']);
        $student2->studentProfile->update(['faculty' => 'medicine']);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/students?faculty=engineering');

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('total'));
        $this->assertEquals('engineering', $response->json('data.0.student_profile.faculty'));
    }

    /**
     * Test admin can filter students by status
     */
    public function test_admin_can_filter_students_by_status(): void
    {
        $activeStudent = $this->createTestStudent(['status' => 'active']);
        $pendingStudent = $this->createTestStudent(['status' => 'pending']);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/students?status=active');

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('total'));
        $this->assertEquals('active', $response->json('data.0.status'));
    }

    /**
     * Test admin can search students
     */
    public function test_admin_can_search_students(): void
    {
        $student1 = $this->createTestStudent(['name' => 'John Doe']);
        $student2 = $this->createTestStudent(['name' => 'Jane Smith']);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/students?search=John');

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('total'));
        $this->assertEquals('John Doe', $response->json('data.0.name'));
    }

    /**
     * Test student cannot access students endpoint
     */
    public function test_student_cannot_access_students_endpoint(): void
    {
        $response = $this->actingAs($this->studentUser)
            ->getJson('/api/students');

        $response->assertStatus(403);
    }

    // ========== Store Tests ==========

    /**
     * Test admin can create student with valid data
     */
    public function test_admin_can_create_student_with_valid_data(): void
    {
        $data = $this->getValidStudentRequestData();

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/students', $data);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'id',
                'name',
                'email',
                'status',
                'student_profile',
                'role'
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'john.doe@test.com',
            'name' => 'John Doe'
        ]);

        $this->assertDatabaseHas('student_profiles', [
            'iin' => '123456789012',
            'faculty' => 'engineering'
        ]);
    }

    /**
     * Test admin can create student with files
     */
    public function test_admin_can_create_student_with_files(): void
    {
        $data = $this->getValidStudentRequestData();
        $data['student_profile']['files'] = [
            UploadedFile::fake()->create('document1.pdf', 1000, 'application/pdf'),
            UploadedFile::fake()->create('document2.pdf', 1000, 'application/pdf'),
            UploadedFile::fake()->image('avatar.jpg', 200, 300)
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/students', $data);

        $response->assertStatus(201);

        $student = User::find($response->json('id'));
        $this->assertNotNull($student->studentProfile->files);
        $this->assertCount(3, $student->studentProfile->files);
    }

    /**
     * Test admin can create student with bed assignment
     */
    public function test_admin_can_create_student_with_bed_assignment(): void
    {
        $room = Room::factory()->create(['dormitory_id' => $this->testDormitory->id]);
        $bed = Bed::factory()->create(['room_id' => $room->id, 'is_occupied' => false, 'bed_number' => 99]);

        $data = $this->getValidStudentRequestData();
        $data['bed_id'] = $bed->id;

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/students', $data);

        $response->assertStatus(201);

        $bed->refresh();
        $this->assertTrue($bed->is_occupied, 'Bed should be occupied');
        $this->assertEquals($response->json('id'), $bed->user_id, 'Bed should be assigned to the created student');
    }

    /**
     * Test creating student with invalid data fails
     */
    public function test_create_student_with_invalid_data_fails(): void
    {
        $data = [
            'email' => 'invalid-email',
            'first_name' => '',
            'student_profile' => [
                'iin' => '123',
                'faculty' => ''
            ]
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/students', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'first_name', 'student_profile.iin', 'student_profile.faculty']);
    }

    /**
     * Test creating student with duplicate email fails
     */
    public function test_create_student_with_duplicate_email_fails(): void
    {
        $existingStudent = $this->createTestStudent();

        $data = $this->getValidStudentRequestData();
        $data['email'] = $existingStudent->email;

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/students', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    // ========== Show Tests ==========

    /**
     * Test admin can view student details
     */
    public function test_admin_can_view_student_details(): void
    {
        $student = $this->createTestStudent();

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/students/{$student->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'name',
                'email',
                'status',
                'student_profile',
                'role',
                'room',
                'payments'
            ]);

        $this->assertEquals($student->id, $response->json('id'));
    }

    /**
     * Test viewing non-existent student returns 404
     */
    public function test_viewing_non_existent_student_returns_404(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/students/999');

        $response->assertStatus(404);
    }

    // ========== Update Tests ==========

    /**
     * Test admin can update student
     */
    public function test_admin_can_update_student(): void
    {
        $student = $this->createTestStudent();

        $updateData = [
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email' => 'jane.smith@test.com',
            'student_profile' => [
                'faculty' => 'medicine',
                'enrollment_year' => 2023
            ]
        ];

        $response = $this->actingAs($this->adminUser)
            ->putJson("/api/students/{$student->id}", $updateData);

        $response->assertStatus(200)
            ->assertJson(['name' => 'Jane Smith']);

        $this->assertDatabaseHas('users', [
            'id' => $student->id,
            'name' => 'Jane Smith',
            'email' => 'jane.smith@test.com'
        ]);
    }

    /**
     * Test admin can update student bed assignment
     */
    public function test_admin_can_update_student_bed_assignment(): void
    {
        $student = $this->createTestStudent();

        $newRoom = Room::factory()->create(['dormitory_id' => $this->testDormitory->id]);
        $newBed = Bed::factory()->create(['room_id' => $newRoom->id, 'is_occupied' => false]);

        $updateData = [
            'bed_id' => $newBed->id,
            'email' => $student->email
        ];

        $response = $this->actingAs($this->adminUser)
            ->putJson("/api/students/{$student->id}", $updateData);

        $response->assertStatus(200);

        $newBed->refresh();
        $this->assertTrue($newBed->is_occupied);
        $this->assertEquals($student->id, $newBed->user_id);
    }

    /**
     * Test updating student with invalid data fails
     */
    public function test_update_student_with_invalid_data_fails(): void
    {
        $student = $this->createTestStudent();

        $updateData = [
            'email' => 'invalid-email',
            'student_profile' => [
                'enrollment_year' => 'not-a-number'
            ]
        ];

        $response = $this->actingAs($this->adminUser)
            ->putJson("/api/students/{$student->id}", $updateData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'student_profile.enrollment_year']);
    }

    // ========== Destroy Tests ==========

    /**
     * Test admin can delete student
     */
    public function test_admin_can_delete_student(): void
    {
        $student = $this->createTestStudent();

        $response = $this->actingAs($this->adminUser)
            ->deleteJson("/api/students/{$student->id}");

        $response->assertStatus(200)
            ->assertJson(['message' => 'Student deleted successfully']);

        $this->assertSoftDeleted('users', ['id' => $student->id]);
    }

    /**
     * Test deleting non-existent student returns 404
     */
    public function test_deleting_non_existent_student_returns_404(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->deleteJson('/api/students/999');

        $response->assertStatus(404);
    }

    // ========== Export Tests ==========

    /**
     * Test admin can export students
     */
    public function test_admin_can_export_students(): void
    {
        $student = $this->createTestStudent();

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/students/export');

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'text/csv; charset=utf-8')
            ->assertHeader('Content-Disposition');
    }

    /**
     * Test admin can export students with filters
     */
    public function test_admin_can_export_students_with_filters(): void
    {
        $student1 = $this->createTestStudent();
        $student2 = $this->createTestStudent();

        $student1->studentProfile->update(['faculty' => 'engineering']);
        $student2->studentProfile->update(['faculty' => 'medicine']);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/students/export?faculty=engineering');

        $response->assertStatus(200);
        $csvContent = $response->getContent();
        $this->assertStringContainsString('engineering', $csvContent);
        $this->assertStringNotContainsString('medicine', $csvContent);
    }

    // ========== Approve Tests ==========

    /**
     * Test admin can approve student
     */
    public function test_admin_can_approve_student(): void
    {
        $student = $this->createTestStudent(['status' => 'pending']);

        $response = $this->actingAs($this->adminUser)
            ->patchJson("/api/students/{$student->id}/approve");

        $response->assertStatus(200)
            ->assertJson(['message' => 'Student approved successfully']);

        $student->refresh();
        $this->assertEquals('active', $student->status);
    }

    /**
     * Test approving already active student
     */
    public function test_approving_already_active_student(): void
    {
        $student = $this->createTestStudent(['status' => 'active']);

        $response = $this->actingAs($this->adminUser)
            ->patchJson("/api/students/{$student->id}/approve");

        $response->assertStatus(200);
        $this->assertEquals('active', $student->fresh()->status);
    }

    // ========== List All Tests ==========

    /**
     * Test admin can list all students (paginated)
     */
    public function test_admin_can_list_all_students(): void
    {
        $this->createTestStudent(['name' => 'Student A']);
        $this->createTestStudent(['name' => 'Student B']);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/students-list');

        $response->assertStatus(200)
            ->assertJsonCount(2);

        $names = collect($response->json())->pluck('name')->toArray();
        $this->assertContains('Student A', $names);
        $this->assertContains('Student B', $names);
    }

    // ========== Authorization Tests ==========

    /**
     * Test unauthenticated user cannot access student endpoints
     */
    public function test_unauthenticated_user_cannot_access_student_endpoints(): void
    {
        // Test various endpoints without authentication
        $this->getJson('/api/students')->assertStatus(401);
        $this->postJson('/api/students', [])->assertStatus(401);
        $this->getJson('/api/students/999')->assertStatus(401);
        $this->putJson('/api/students/999', [])->assertStatus(401);
        $this->deleteJson('/api/students/999')->assertStatus(401);
        $this->getJson('/api/students/export')->assertStatus(401);
        $this->patchJson('/api/students/999/approve')->assertStatus(401);
        $this->getJson('/api/students-list')->assertStatus(401);
    }

    /**
     * Test student cannot manage other students
     */
    public function test_student_cannot_manage_other_students(): void
    {
        $otherStudent = $this->createTestStudent();

        $data = $this->getValidStudentRequestData();

        $this->actingAs($this->studentUser)
            ->postJson('/api/students', $data)
            ->assertStatus(403);

        $this->actingAs($this->studentUser)
            ->putJson("/api/students/{$otherStudent->id}", ['first_name' => 'Test'])
            ->assertStatus(403);

        $this->actingAs($this->studentUser)
            ->deleteJson("/api/students/{$otherStudent->id}")
            ->assertStatus(403);

        $this->actingAs($this->studentUser)
            ->patchJson("/api/students/{$otherStudent->id}/approve")
            ->assertStatus(403);
    }

    // ========== Helper Methods ==========

    private function getValidStudentRequestData(): array
    {
        return [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'phone_numbers' => ['+1234567890'],
            'gender' => 'male',
            'student_profile' => [
                'iin' => '123456789012',
                'faculty' => 'engineering',
                'specialist' => 'computer_science',
                'enrollment_year' => 2024,
                'agree_to_dormitory_rules' => true,
                'has_meal_plan' => true,
                'gender' => 'male',
                'identification_type' => 'passport',
                'identification_number' => 'PASS123456',
                'blood_type' => 'A+',
                'country' => 'Kazakhstan',
                'city' => 'Almaty',
                'emergency_contact_name' => 'Parent Name',
                'emergency_contact_phone' => '+1234567891',
                'emergency_contact_type' => 'parent',
                'emergency_contact_email' => 'parent@test.com',
                'emergency_contact_relationship' => 'parent'
            ]
        ];
    }

    private function createTestStudent(array $overrides = []): User
    {
        $studentRole = Role::where('name', 'student')->first();

        $student = User::factory()->create(array_merge([
            'name' => 'Test Student',
            'email' => 'student_' . uniqid() . '@test.com',
            'role_id' => $studentRole->id,
            'status' => 'pending',
            'dormitory_id' => $this->testDormitory->id
        ], $overrides));

        StudentProfile::factory()->create([
            'user_id' => $student->id,
            'iin' => str_pad((string) rand(1, 999999999999), 12, '0', STR_PAD_LEFT)
        ]);

        return $student->fresh(['studentProfile']);
    }
}
