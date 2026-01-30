<?php

namespace Tests\Unit\Services;

use App\Models\Bed;
use App\Models\Dormitory;
use App\Models\Role;
use App\Models\Room;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\StudentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * @coversDefaultClass \App\Services\StudentService
 */
class StudentServiceTest extends TestCase
{
    use RefreshDatabase;

    private StudentService $studentService;
    private User $adminUser;
    private User $sudoUser;
    private Dormitory $testDormitory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->studentService = app(StudentService::class);

        // Setup test data
        $this->seedTestData();
    }

    private function seedTestData(): void
    {
        // Create roles
        $studentRole = Role::factory()->create([ 'name' => 'student' ]);
        $adminRole = Role::factory()->create([ 'name' => 'admin' ]);
        $sudoRole = Role::factory()->create([ 'name' => 'sudo' ]);

        // Create dormitory
        $this->testDormitory = Dormitory::factory()->create([
            'name'   => 'Test Dormitory',
            'gender' => 'male'
        ]);

        // Create users
        $this->adminUser = User::factory()->create([
            'name'         => 'Admin User',
            'email'        => 'admin@test.com',
            'role_id'      => $adminRole->id,
            'dormitory_id' => $this->testDormitory->id
        ]);

        $this->sudoUser = User::factory()->create([
            'name'    => 'Sudo User',
            'email'   => 'sudo@test.com',
            'role_id' => $sudoRole->id
        ]);
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
            'last_name'  => 'Doe'
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
            'last_name'  => '  Doe  '
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
            'last_name'  => ''
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
            'last_name'  => 'Doe'
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

    // ========== Student Creation Tests ==========

    /**
     * Test student creation with valid data
     */
    public function test_create_student_with_valid_data(): void
    {
        $data = $this->getValidStudentData();

        $student = $this->studentService->createStudent($data, $this->testDormitory);

        $this->assertInstanceOf(User::class, $student);
        $this->assertEquals('John Doe', $student->name);
        $this->assertEquals('john.doe@test.com', $student->email);
        $this->assertEquals('student', $student->role->name);
        $this->assertEquals('pending', $student->status);
        $this->assertEquals($this->testDormitory->id, $student->dormitory_id);

        // Check student profile was created
        $this->assertInstanceOf(StudentProfile::class, $student->studentProfile);
        $this->assertEquals('123456789012', $student->studentProfile->iin);
        $this->assertEquals('engineering', $student->studentProfile->faculty);
    }

    /**
     * Test student creation with bed assignment
     */
    public function test_create_student_with_bed_assignment(): void
    {
        // Create room and bed
        $room = Room::factory()->create([ 'dormitory_id' => $this->testDormitory->id ]);
        $bed = Bed::factory()->create([ 'room_id' => $room->id, 'is_occupied' => false ]);

        $data = $this->getValidStudentData();
        $data['bed_id'] = $bed->id;

        $student = $this->studentService->createStudent($data, $this->testDormitory);

        $this->assertEquals($room->id, $student->room_id);
        $bed->refresh();
        $this->assertTrue($bed->is_occupied);
        $this->assertEquals($student->id, $bed->user_id);
    }

    /**
     * Test student creation with gender incompatible with dormitory
     */
    public function test_create_student_gender_incompatible_with_dormitory(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('The selected dormitory does not accept students of this gender.');

        $data = $this->getValidStudentData();
        $data['gender'] = 'female'; // Dormitory is male-only

        $this->studentService->createStudent($data, $this->testDormitory);
    }

    // ========== Student Update Tests ==========

    /**
     * Test student update with valid data
     */
    public function test_update_student_with_valid_data(): void
    {
        // Create a student first
        $student = $this->createTestStudent();

        $updateData = [
            'first_name'      => 'Jane',
            'last_name'       => 'Smith',
            'email'           => 'jane.smith@test.com',
            'student_profile' => [
                'faculty'         => 'medicine',
                'enrollment_year' => 2023
            ]
        ];

        $result = $this->studentService->updateStudent($student->id, $updateData, $this->adminUser);
        $updatedStudent = $result['user'];

        $this->assertEquals('Jane Smith', $updatedStudent->name);
        $this->assertEquals('jane.smith@test.com', $updatedStudent->email);
        $this->assertEquals('medicine', $updatedStudent->studentProfile->faculty);
        $this->assertEquals(2023, $updatedStudent->studentProfile->enrollment_year);
    }

    /**
     * Test student update with bed reassignment
     */
    public function test_update_student_with_bed_reassignment(): void
    {
        $student = $this->createTestStudent();

        // Create new room and bed
        $newRoom = Room::factory()->create([ 'dormitory_id' => $this->testDormitory->id ]);
        $newBed = Bed::factory()->create([ 'room_id' => $newRoom->id, 'is_occupied' => false ]);

        $updateData = [ 'bed_id' => $newBed->id ];

        $result = $this->studentService->updateStudent($student->id, $updateData, $this->sudoUser);
        $updatedStudent = $result['user'];

        $this->assertEquals($newRoom->id, $updatedStudent->room_id);
        $newBed->refresh();
        $this->assertTrue($newBed->is_occupied);
        $this->assertEquals($student->id, $newBed->user_id);
    }

    /**
     * Test student update with gender incompatible dormitory
     */
    public function test_update_student_gender_incompatible_with_dormitory(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('The selected dormitory does not accept students of this gender.');

        $student = $this->createTestStudent();

        // Create female dormitory
        $femaleDormitory = Dormitory::factory()->create([ 'gender' => 'female' ]);
        $femaleRoom = Room::factory()->create([ 'dormitory_id' => $femaleDormitory->id ]);
        $femaleBed = Bed::factory()->create([ 'room_id' => $femaleRoom->id ]);

        $updateData = [
            'bed_id' => $femaleBed->id,
            'gender' => 'male' // Student is male but trying to assign to female dormitory
        ];

        $this->studentService->updateStudent($student->id, $updateData, $this->sudoUser);
    }

    // ========== Student Retrieval Tests ==========

    /**
     * Test get students with filters
     */
    public function test_get_students_with_filters(): void
    {
        // Create multiple students
        $student1 = $this->createTestStudent([ 'name' => 'John Doe', 'email' => 'john@test.com' ]);
        $student2 = $this->createTestStudent([ 'name' => 'Jane Smith', 'email' => 'jane@test.com' ]);

        // Update profiles with different faculties
        $student1->studentProfile->update([ 'faculty' => 'engineering' ]);
        $student2->studentProfile->update([ 'faculty' => 'medicine' ]);

        // Test faculty filter
        $filters = [ 'faculty' => 'engineering' ];
        $result = $this->studentService->getStudentsWithFilters($this->sudoUser, $filters);

        $this->assertEquals(1, $result->total());
        $this->assertEquals('John Doe', $result->first()->name);
    }

    /**
     * Test get students with admin user (should only see dormitory students)
     */
    public function test_get_students_with_admin_user(): void
    {
        // Create student in admin's dormitory
        $studentInDormitory = $this->createTestStudent([ 'dormitory_id' => $this->testDormitory->id ]);

        // Create student in different dormitory
        $otherDormitory = Dormitory::factory()->create();
        $studentOutsideDormitory = $this->createTestStudent([ 'dormitory_id' => $otherDormitory->id ]);

        $result = $this->studentService->getStudentsWithFilters($this->adminUser, []);

        $this->assertEquals(1, $result->total());
        $this->assertEquals($studentInDormitory->id, $result->first()->id);
    }

    /**
     * Test get student details
     */
    public function test_get_student_details(): void
    {
        $student = $this->createTestStudent();

        $details = $this->studentService->getStudentDetails($student->id);

        $this->assertEquals($student->id, $details->id);
        $this->assertTrue($details->relationLoaded('studentProfile'));
        $this->assertTrue($details->relationLoaded('role'));
        $this->assertTrue($details->relationLoaded('room'));
    }

    // ========== Student Deletion Tests ==========

    /**
     * Test student deletion
     */
    public function test_delete_student(): void
    {
        $student = $this->createTestStudent();
        $studentId = $student->id;

        $result = $this->studentService->deleteStudent($studentId);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('users', [ 'id' => $studentId ]);
    }

    /**
     * Test student deletion frees up bed
     */
    public function test_delete_student_frees_up_bed(): void
    {
        $room = Room::factory()->create([ 'dormitory_id' => $this->testDormitory->id ]);
        $bed = Bed::factory()->create([ 'room_id' => $room->id, 'is_occupied' => true ]);

        $student = $this->createTestStudent([ 'room_id' => $room->id ]);
        $bed->update([ 'user_id' => $student->id ]);

        $this->studentService->deleteStudent($student->id);

        $bed->refresh();
        $this->assertFalse($bed->is_occupied);
        $this->assertNull($bed->user_id);
    }

    // ========== Student Approval Tests ==========

    /**
     * Test student approval
     */
    public function test_approve_student(): void
    {
        $student = $this->createTestStudent([ 'status' => 'pending' ]);

        $approvedStudent = $this->studentService->approveStudent($student->id);

        $this->assertEquals('active', $approvedStudent->status);
    }

    // ========== Export Tests ==========

    /**
     * Test student export
     */
    public function test_export_students(): void
    {
        $student = $this->createTestStudent();
        $student->studentProfile->update([ 'faculty' => 'engineering' ]);

        $filters = [ 'faculty' => 'engineering' ];
        $response = $this->studentService->exportStudents($this->sudoUser, $filters);

        $this->assertEquals('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment; filename=', $response->headers->get('Content-Disposition'));
    }

    // ========== Statistics Tests ==========

    /**
     * Test get student statistics
     */
    public function test_get_student_statistics(): void
    {
        // Create students with different statuses
        $this->createTestStudent([ 'status' => 'active' ]);
        $this->createTestStudent([ 'status' => 'pending' ]);
        $this->createTestStudent([ 'status' => 'suspended' ]);

        $stats = $this->studentService->getStudentStatistics($this->sudoUser, []);

        $this->assertEquals(3, $stats['total']);
        $this->assertEquals(1, $stats['active']);
        $this->assertEquals(1, $stats['pending']);
        $this->assertEquals(1, $stats['suspended']);
    }

    // ========== Helper Methods ==========

    private function getValidStudentData(): array
    {
        return [
            'first_name'            => 'John',
            'last_name'             => 'Doe',
            'email'                 => 'john.doe@test.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
            'gender'                => 'male',
            'student_profile'       => [
                'iin'                      => '123456789012',
                'faculty'                  => 'engineering',
                'specialist'               => 'computer_science',
                'enrollment_year'          => 2024,
                'agree_to_dormitory_rules' => true,
                'has_meal_plan'            => true,
                'identification_type'      => 'passport',
                'identification_number'    => 'PASS123456'
            ]
        ];
    }

    private function createTestStudent(array $overrides = []): User
    {
        $studentRole = Role::where('name', 'student')->first();

        $student = User::factory()->create(array_merge([
            'name'         => 'Test Student',
            'email'        => 'student@test.com',
            'role_id'      => $studentRole->id,
            'status'       => 'pending',
            'dormitory_id' => $this->testDormitory->id
        ], $overrides));

        StudentProfile::factory()->create([
            'user_id' => $student->id,
            'iin'     => '123456789012'
        ]);

        return $student->fresh([ 'studentProfile' ]);
    }
}
