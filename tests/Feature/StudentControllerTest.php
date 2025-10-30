<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\Dormitory;
use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Testing\File;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StudentControllerTest extends TestCase {
	use RefreshDatabase;

	private User $sudo;
	private User $admin;
	private User $student;
	private Dormitory $dormitory;

	protected function setUp(): void {
		parent::setUp();

		// Create roles
		$sudoRole = Role::create( [ 'name' => 'sudo' ] );
		$adminRole = Role::create( [ 'name' => 'admin' ] );
		$studentRole = Role::create( [ 'name' => 'student' ] );

		// Create test users
		$this->sudo = User::factory()->create( [ 
			'role_id' => $sudoRole->id,
			'email'   => 'sudo@test.com'
		] );

		$this->admin = User::factory()->create( [ 
			'role_id' => $adminRole->id,
			'email'   => 'admin@test.com'
		] );

		$this->student = User::factory()->create( [ 
			'role_id' => $studentRole->id,
			'email'   => 'student@test.com'
		] );

		// Create StudentProfile for the test student
		\App\Models\StudentProfile::create( [ 
			'user_id'                  => $this->student->id,
			'iin'                      => '123456789012',
			'student_id'               => 'STU00001',
			'faculty'                  => 'Engineering',
			'specialist'               => 'Computer Science',
			'enrollment_year'          => '2024',
			'gender'                   => 'male',
			'blood_type'               => 'O+',
			'parent_name'              => 'Parent Name',
			'parent_phone'             => '+77012345678',
			'mentor_name'              => 'Mentor Name',
			'mentor_email'             => 'mentor@test.com',
			'deal_number'              => 'DEAL001',
			'agree_to_dormitory_rules' => true,
			'files'                    => json_encode( [] ),
		] );

		// Create dormitory and room
		$this->dormitory = Dormitory::create( [ 
			'name'     => 'Test Dormitory',
			'address'  => 'Test Address',
			'admin_id' => $this->admin->id,
			'gender'   => 'mixed',
			'capacity' => 200,
			'quota'    => 100,
		] );

		$roomType = RoomType::create( [ 
			'name' => 'standard',
			'beds' => [ [ 'id' => 1, 'x' => 50, 'y' => 50 ] ]
		] );

		Room::create( [ 
			'number'       => '101',
			'dormitory_id' => $this->dormitory->id,
			'room_type_id' => $roomType->id,
			'floor'        => 1,
		] );
	}

	#[Test]
	public function sudo_can_view_all_students(): void {
		$response = $this->actingAs( $this->sudo, 'sanctum' )
			->getJson( '/api/students' );

		$response->assertStatus( 200 )
			->assertJsonStructure( [ 
				'data' => [ 
					'*' => [ 
						'id',
						'name',
						'email',
						'faculty',
						'status',
						'role'
					]
				]
			] );
	}

	#[Test]
	public function admin_can_view_students_in_their_dormitory(): void {
		$response = $this->actingAs( $this->admin, 'sanctum' )
			->getJson( '/api/students' );

		$response->assertStatus( 200 );
	}

	#[Test]
	public function regular_student_cannot_view_students_list(): void {
		$response = $this->actingAs( $this->student, 'sanctum' )
			->getJson( '/api/students' );

		$response->assertStatus( 403 );
	}

	#[Test]
	public function admin_can_create_new_student(): void {
		Storage::fake( 'public' );

		$room = Room::where('dormitory_id', $this->dormitory->id)->first();
		$bed = $room->beds()->create(['bed_number' => 1]);

		$studentData = [ 
			'first_name' => 'Test',
			'last_name' => 'Student',
			'email' => 'newstudent@test.com',
			'password' => 'password123',
			'password_confirmation' => 'password123',
			'iin' => '987654321098',
			'faculty' => 'Computer Science',
			'specialist' => 'Software Engineering',
			'enrollment_year' => 2024,
			'gender' => 'male',
			'bed_id' => $bed->id,
			'deal_number' => 'DEAL-002',
			'agree_to_dormitory_rules' => true,
			'has_meal_plan' => true,
			'files' => [
				UploadedFile::fake()->image('doc1.jpg')
			]
		];

		$response = $this->actingAs( $this->admin, 'sanctum' )
			->postJson( '/api/students', $studentData );

		$response->assertStatus( 201 )
			->assertJsonFragment( [ 
				'name'  => 'Test Student', // This is auto-generated
				'email' => 'newstudent@test.com'
			] );

		$this->assertDatabaseHas( 'student_profiles', [ 
			'faculty'         => 'Computer Science',
			'specialist'      => 'Software Engineering',
			'enrollment_year' => 2024,
			'iin'             => '987654321098'
		] );

		$student = User::where('email', 'newstudent@test.com')->first();
		$this->assertEquals($this->dormitory->id, $student->dormitory_id);
		Storage::disk('public')->assertExists($student->studentProfile->files[0]);
	}

	#[Test]
	public function admin_can_approve_student(): void {
		$response = $this->actingAs( $this->admin, 'sanctum' )
			->patchJson( "/api/students/{$this->student->id}/approve" );

		$response->assertStatus( 200 )
			->assertJsonFragment( [ 
				'message' => 'Student approved successfully'
			] );

		$this->assertDatabaseHas( 'users', [ 
			'id'     => $this->student->id,
			'status' => 'active'
		] );
	}

	#[Test]
	public function admin_can_update_student_information(): void {
		Storage::fake('public');

		$updateData = [ 
			'first_name' => 'Updated',
			'last_name'  => 'Student',
			'email'      => $this->student->email, // Add email to satisfy validation
			'faculty'    => 'Updated Faculty',
			'blood_type' => 'A+',
			'files' => [
				UploadedFile::fake()->image('updated_doc.jpg')
			]
		];

		$response = $this->actingAs( $this->admin, 'sanctum' )
			->putJson( "/api/students/{$this->student->id}", $updateData );

		$response->assertStatus( 200 )
			->assertJsonFragment( [
				'name' => 'Updated Student',
			] );

		$this->student->refresh();
		Storage::disk('public')->assertExists($this->student->studentProfile->files[0]);
		$this->assertDatabaseHas( 'student_profiles', [ 
			'user_id'    => $this->student->id,
			'faculty'    => 'Updated Faculty',
			'blood_type' => 'A+',
		] );
	}

	#[Test]
	public function admin_can_delete_student(): void {
		$testStudent = User::factory()->create( [ 
			'role_id' => Role::where( 'name', 'student' )->first()->id
		] );

		$response = $this->actingAs( $this->admin, 'sanctum' )
			->deleteJson( "/api/students/{$testStudent->id}" );

		$response->assertStatus( 200 )
			->assertJson( [ 'message' => 'Student deleted successfully' ] );
		// Check that the student is soft deleted
		$this->assertSoftDeleted( 'users', [ 'id' => $testStudent->id ] );
	}

	#[Test]
	public function students_can_be_filtered_by_faculty(): void {
		User::factory()->create( [ 
			'role_id' => Role::where( 'name', 'student' )->first()->id,
		] );
		\App\Models\StudentProfile::factory()->create( [ 
			'faculty' => 'Engineering',
		] );

		$response = $this->actingAs( $this->admin, 'sanctum' )
			->getJson( '/api/students?faculty=Engineering' );

		$response->assertStatus( 200 );
		// Additional assertions for filtering can be added
	}

	#[Test]
	public function validation_errors_are_returned_for_invalid_student_data(): void {
		$invalidData = [ 
			'first_name'  => '', // Required field empty
			'email' => 'invalid-email', // Invalid email format
			'iin'   => '123', // Invalid IIN length
		];

		$response = $this->actingAs( $this->admin, 'sanctum' )
			->postJson( '/api/students', $invalidData );

		$response->assertStatus( 422 )
			->assertJsonValidationErrors( [ 'name', 'email', 'iin' ] );
	}

	#[Test]
	public function student_update_succeeds_with_nested_payload_structure(): void {
		// This test verifies that nested payload structure now works correctly
		$nestedPayload = [
			'first_name' => 'Updated Name',
			'last_name' => 'Student',
			'email' => 'updated@test.com',
			'phone_numbers' => ['+77012345678'],
			'student_profile' => [
				'faculty' => 'Updated Faculty',
				'blood_type' => 'A+'
			]
		];

		$response = $this->actingAs( $this->admin, 'sanctum' )
			->putJson( "/api/students/{$this->student->id}", $nestedPayload );

		// The response should succeed and data should be updated
		$response->assertStatus( 200 )
			->assertJsonFragment(['name' => 'Updated Name Student']);
		
		// The student data should be updated
		$this->assertDatabaseHas( 'users', [
			'id' => $this->student->id,
			'email' => 'updated@test.com'
		]);
		
		// Profile data should also be updated
		$this->assertDatabaseHas( 'student_profiles', [
			'user_id' => $this->student->id,
			'faculty' => 'Updated Faculty',
			'blood_type' => 'A+'
		]);
	}

	#[Test]
	public function student_update_succeeds_with_flat_payload_structure(): void {
		// This test shows the correct flat structure that should work
		$flatPayload = [
			'first_name' => 'Updated',
			'last_name' => 'Name',
			'email' => 'updated@test.com',
			'phone_numbers' => ['+77012345678'],
			'faculty' => 'Updated Faculty',
			'blood_type' => 'A+'
		];

		$response = $this->actingAs( $this->admin, 'sanctum' )
			->putJson( "/api/students/{$this->student->id}", $flatPayload );

		$response->assertStatus( 200 )
			->assertJsonFragment(['name' => 'Updated Name']);

		// The student data should be updated
		$this->assertDatabaseHas( 'users', [
			'id' => $this->student->id,
			'email' => 'updated@test.com'
		]);

		$this->assertDatabaseHas( 'student_profiles', [
			'user_id' => $this->student->id,
			'faculty' => 'Updated Faculty',
			'blood_type' => 'A+'
		]);
	}
}
