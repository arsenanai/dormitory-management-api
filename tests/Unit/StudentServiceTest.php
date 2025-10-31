<?php

namespace Tests\Unit;

use App\Models\Bed;
use App\Models\Dormitory;
use App\Models\Role;
use App\Models\Room;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\StudentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StudentServiceTest extends TestCase
{
    use RefreshDatabase;

    private StudentService $studentService;
    private User $admin;
    private Dormitory $dormitory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->studentService = new StudentService();

        // Seed roles
        Role::factory()->create(['name' => 'admin']);
        Role::factory()->create(['name' => 'student']);

        // Create a dormitory
        $this->dormitory = Dormitory::factory()->create();

        // Create an admin for the dormitory
        $this->admin = User::factory()->create([
            'role_id' => Role::where('name', 'admin')->first()->id,
        ]);
        $this->dormitory->admin_id = $this->admin->id;
        $this->dormitory->save();
        $this->admin->refresh();
    }

    private function createStudentInDormitory(array $userData = [], array $profileData = []): User
    {
        $roomNumber = $userData['room_number'] ?? 'A101';
        unset($userData['room_number']); // Prevent trying to save on User model
        $room = Room::factory()->create(['dormitory_id' => $this->dormitory->id, 'number' => $roomNumber]);

        // The RoomFactory now creates beds automatically.
        // We should retrieve one of those beds instead of creating a new one.
        $bed = $room->beds()->first();
        $this->assertNotNull($bed, "Bed should have been created by the RoomFactory.");

        $student = User::factory()->create(array_merge([
            'role_id' => Role::where('name', 'student')->first()->id,
            'dormitory_id' => $this->dormitory->id,
            'room_id' => $room->id,
        ], $userData));

        StudentProfile::factory()->create(array_merge([
            'user_id' => $student->id,
        ], $profileData));

        $student->studentBed()->save($bed);

        return $student->refresh();
    }

    public function test_get_students_with_filters()
    {
        $student1 = $this->createStudentInDormitory([], ['faculty' => 'Engineering']);
        $student2 = $this->createStudentInDormitory([], ['faculty' => 'Medicine']);

        // Test no filters
        $response = $this->studentService->getStudentsWithFilters([], $this->admin);
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertCount(2, $response->getData()->data);

        // Test filter by faculty
        $response = $this->studentService->getStudentsWithFilters(['faculty' => 'Engineering'], $this->admin);
        $this->assertCount(1, $response->getData()->data);
        $this->assertEquals($student1->id, $response->getData()->data[0]->id);

        // Test filter by status
        $student1->status = 'active';
        $student1->save();
        $response = $this->studentService->getStudentsWithFilters(['status' => 'active'], $this->admin);
        $this->assertCount(1, $response->getData()->data);
        $this->assertEquals($student1->id, $response->getData()->data[0]->id);
    }

    public function test_create_student()
    {
        Storage::fake('public');

        $room = Room::factory()->create(['dormitory_id' => $this->dormitory->id]);
        // The RoomFactory now creates beds automatically.
        // We should retrieve one of those beds instead of creating a new one.
        $bed = $room->beds()->first();

        $studentData = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'password' => 'password',
            'iin' => '123456789012',
            'faculty' => 'Engineering',
            'specialist' => 'Software Engineering',
            'enrollment_year' => 2023,
            'gender' => 'male',
            'bed_id' => $bed->id,
            'files' => [UploadedFile::fake()->image('test.jpg')]
        ];

        $response = $this->studentService->createStudent($studentData, $this->dormitory);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(201, $response->getStatusCode());
        $this->assertDatabaseHas('users', ['email' => 'john.doe@example.com']);
        $this->assertDatabaseHas('student_profiles', ['iin' => '123456789012']);

        $student = User::where('email', 'john.doe@example.com')->first();
        $this->assertEquals($room->id, $student->room_id);
        $this->assertEquals($this->dormitory->id, $student->dormitory_id);

        $bed->refresh();
        $this->assertTrue($bed->is_occupied);
        $this->assertEquals($student->id, $bed->user_id);

        $profile = $student->studentProfile;
        $this->assertCount(1, $profile->files);
        Storage::disk('public')->assertExists($profile->files[0]);
    }

    public function test_update_student()
    {
        Storage::fake('public');
        $student = $this->createStudentInDormitory();

        $newRoom = Room::factory()->create(['dormitory_id' => $this->dormitory->id]);
        $newBed = $newRoom->beds()->first();

        $updateData = [
            'first_name' => 'Jane',
            'faculty' => 'Medicine',
            'bed_id' => $newBed->id,
            'files' => [UploadedFile::fake()->image('new.jpg')]
        ];

        $response = $this->studentService->updateStudent($student->id, $updateData, $this->admin);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        $student->refresh();
        $this->assertEquals('Jane', $student->first_name);
        $this->assertEquals('Medicine', $student->studentProfile->faculty);
        $this->assertEquals($newRoom->id, $student->room_id);
        $this->assertEquals($newBed->id, $student->studentBed->id);

        $this->assertCount(1, $student->studentProfile->files);
        Storage::disk('public')->assertExists($student->studentProfile->files[0]);
    }

    public function test_update_student_unassigns_bed()
    {
        $student = $this->createStudentInDormitory();
        $oldBed = $student->studentBed;

        $updateData = [
            'bed_id' => null,
        ];

        $this->studentService->updateStudent($student->id, $updateData, $this->admin);

        $student->refresh();
        $this->assertNull($student->studentBed);
        $this->assertNull($student->room_id);

        $oldBed->refresh();
        $this->assertFalse($oldBed->is_occupied);
        $this->assertNull($oldBed->user_id);
    }

    public function test_get_student_details()
    {
        $student = $this->createStudentInDormitory();

        $response = $this->studentService->getStudentDetails($student->id);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals($student->id, $response->getData()->id);
        $this->assertObjectHasProperty('student_profile', $response->getData());
        $this->assertObjectHasProperty('room', $response->getData());
    }

    public function test_delete_student()
    {
        Storage::fake('public');
        $student = $this->createStudentInDormitory();
        $profile = $student->studentProfile;
        $file = UploadedFile::fake()->image('test.jpg')->store('student_files', 'public');
        $profile->files = [$file];
        $profile->save();

        $bed = $student->studentBed;

        $response = $this->studentService->deleteStudent($student->id);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSoftDeleted('users', ['id' => $student->id]);

        // Check that bed is freed
        $bed->refresh();
        $this->assertFalse($bed->is_occupied);
        $this->assertNull($bed->user_id);

        // Check that file is deleted
        Storage::disk('public')->assertMissing($file);
    }

    public function test_approve_student()
    {
        $student = $this->createStudentInDormitory(['status' => 'pending']);

        $response = $this->studentService->approveStudent($student->id);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals('active', $response->getData()->student->status);

        $student->refresh();
        $this->assertEquals('active', $student->status);
    }

    public function test_export_students()
    {
        // Set a predictable name for the dormitory for this test
        $this->dormitory->name = 'Test Export Dorm'; // Set name
        $this->dormitory->save(); // Save it
        $this->createStudentInDormitory([
            'name' => 'John Doe',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@test.com',
            'phone_numbers' => ['111-222-333'],
            'room_number' => 'A101' // Pass predictable room number
        ], [
            'iin' => '111222333444',
            'faculty' => 'Engineering',
            'specialist' => 'CS',
            'enrollment_year' => 2022,
            'gender' => 'male',
            'city' => 'Almaty'
        ]);

        $response = $this->studentService->exportStudents([], $this->admin);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));

        $content = $response->getContent();
        
        $this->assertStringContainsString('IIN,Name,Faculty,Specialist,Enrollment Year,Gender,Email,Phone Numbers,Status,Room,Dormitory,City', $content);
        $this->assertStringContainsString('111222333444,"John Doe","Engineering","CS",2022,male,john@test.com,"111-222-333",pending,"A101","Test Export Dorm","Almaty"', $content);
    }

    public function test_get_students_by_dormitory()
    {
        $dormitory2 = Dormitory::factory()->create();
        $this->createStudentInDormitory(); // In $this->dormitory
        
        // Create student in another dormitory
        $room2 = Room::factory()->create(['dormitory_id' => $dormitory2->id]);
        User::factory()->create([
            'role_id' => Role::where('name', 'student')->first()->id,
            'dormitory_id' => $dormitory2->id,
            'room_id' => $room2->id,
        ]);

        $response = $this->studentService->getStudentsByDormitory(['dormitory_id' => $this->dormitory->id], $this->admin);
        
        $this->assertCount(1, $response->getData()->data);
        $this->assertEquals($this->dormitory->id, $response->getData()->data[0]->dormitory_id);
    }

    public function test_get_unassigned_students()
    {
        // Assigned student
        $this->createStudentInDormitory();

        // Unassigned student
        User::factory()->create([
            'role_id' => Role::where('name', 'student')->first()->id,
            'room_id' => null,
            'dormitory_id' => null,
        ]);

        $response = $this->studentService->getUnassignedStudents();
        $this->assertCount(1, $response->getData()->data);
        $this->assertNull($response->getData()->data[0]->room_id);
    }

    public function test_update_student_access()
    {
        $student = $this->createStudentInDormitory(['status' => 'pending']);

        // Grant access
        $response = $this->studentService->updateStudentAccess($student->id, ['has_access' => true]);
        $this->assertEquals('active', $response->getData()->student->status);

        // Revoke access
        $response = $this->studentService->updateStudentAccess($student->id, ['has_access' => false]);
        $this->assertEquals('suspended', $response->getData()->student->status);
    }

    public function test_get_student_statistics()
    {
        $this->createStudentInDormitory(['status' => 'active']);
        $this->createStudentInDormitory(['status' => 'active']);
        $this->createStudentInDormitory(['status' => 'pending']);
        $this->createStudentInDormitory(['status' => 'suspended']);

        // Create student in another dormitory to test filtering
        $dormitory2 = Dormitory::factory()->create();
        $room2 = Room::factory()->create(['dormitory_id' => $dormitory2->id]);
        User::factory()->create([
            'role_id' => Role::where('name', 'student')->first()->id,
            'dormitory_id' => $dormitory2->id,
            'room_id' => $room2->id,
            'status' => 'active'
        ]);

        // Test stats for the admin's dormitory
        $response = $this->studentService->getStudentStatistics([], $this->admin);
        $stats = $response->getData();

        $this->assertEquals(4, $stats->total);
        $this->assertEquals(2, $stats->active);
        $this->assertEquals(1, $stats->pending);
        $this->assertEquals(1, $stats->suspended);

        // Test stats with explicit dormitory filter
        $response = $this->studentService->getStudentStatistics(['dormitory_id' => $dormitory2->id], $this->admin);
        $stats2 = $response->getData();
        $this->assertEquals(1, $stats2->total);
        $this->assertEquals(1, $stats2->active);
    }

    public function test_prepare_user_data_for_create()
    {
        $service = new \ReflectionClass(StudentService::class);
        $method = $service->getMethod('prepareUserData');
        $method->setAccessible(true);

        $data = [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'faculty' => 'Should be ignored'
        ];

        $userData = $method->invokeArgs($this->studentService, [$data, null]);

        $this->assertArrayHasKey('password', $userData);
        $this->assertTrue(\Hash::check('password123', $userData['password']));
        $this->assertEquals('pending', $userData['status']);
        $this->assertEquals(Role::where('name', 'student')->first()->id, $userData['role_id']);
        $this->assertEquals('Test User', $userData['name']);
        $this->assertArrayNotHasKey('faculty', $userData);
    }

    public function test_prepare_profile_data_for_update()
    {
        $service = new \ReflectionClass(StudentService::class);
        $method = $service->getMethod('prepareProfileData');
        $method->setAccessible(true);

        $data = [
            'first_name' => 'Should be ignored',
            'faculty' => 'New Faculty',
            'student_id' => 'NEW_ID_123'
        ];

        $profileData = $method->invokeArgs($this->studentService, [$data, true]);

        $this->assertEquals('New Faculty', $profileData['faculty']);
        $this->assertEquals('NEW_ID_123', $profileData['student_id']);
        $this->assertArrayNotHasKey('first_name', $profileData);
    }
}
