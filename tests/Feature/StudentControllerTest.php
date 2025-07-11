<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\Dormitory;
use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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

	/** @test */
	public function sudo_can_view_all_students() {
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

	/** @test */
	public function admin_can_view_students_in_their_dormitory() {
		$response = $this->actingAs( $this->admin, 'sanctum' )
			->getJson( '/api/students' );

		$response->assertStatus( 200 );
	}

	/** @test */
	public function regular_student_cannot_view_students_list() {
		$response = $this->actingAs( $this->student, 'sanctum' )
			->getJson( '/api/students' );

		$response->assertStatus( 403 );
	}

	/** @test */
	public function admin_can_create_new_student() {
		Storage::fake( 'public' );

		$studentData = [ 
			'iin'             => '123456789012',
			'name'            => 'Test Student',
			'faculty'         => 'Computer Science',
			'specialist'      => 'Software Engineering',
			'enrollment_year' => 2024,
			'gender'          => 'male',
			'email'           => 'newstudent@test.com',
			'password'        => 'password123',
			'blood_type'      => 'O+',
			'parent_name'     => 'Test Parent',
			'parent_phone'    => '+77012345678',
		];

		$response = $this->actingAs( $this->admin, 'sanctum' )
			->postJson( '/api/students', $studentData );

		$response->assertStatus( 201 )
			->assertJsonFragment( [ 
				'name'  => 'Test Student',
				'email' => 'newstudent@test.com'
			] );

		$this->assertDatabaseHas( 'users', [ 
			'iin'    => '123456789012',
			'name'   => 'Test Student',
			'status' => 'pending'
		] );
	}

	/** @test */
	public function admin_can_approve_student() {
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

	/** @test */
	public function admin_can_update_student_information() {
		$updateData = [ 
			'name'       => 'Updated Student Name',
			'faculty'    => 'Updated Faculty',
			'blood_type' => 'A+',
		];

		$response = $this->actingAs( $this->admin, 'sanctum' )
			->putJson( "/api/students/{$this->student->id}", $updateData );

		$response->assertStatus( 200 )
			->assertJsonFragment( [ 
				'name'    => 'Updated Student Name',
				'faculty' => 'Updated Faculty'
			] );
	}

	/** @test */
	public function admin_can_delete_student() {
		$testStudent = User::factory()->create( [ 
			'role_id' => Role::where( 'name', 'student' )->first()->id
		] );

		$response = $this->actingAs( $this->admin, 'sanctum' )
			->deleteJson( "/api/students/{$testStudent->id}" );

		$response->assertStatus( 204 );
		// Check that the student is soft deleted
		$this->assertSoftDeleted( 'users', [ 'id' => $testStudent->id ] );
	}

	/** @test */
	public function students_can_be_filtered_by_faculty() {
		User::factory()->create( [ 
			'role_id' => Role::where( 'name', 'student' )->first()->id,
			'faculty' => 'Engineering'
		] );

		$response = $this->actingAs( $this->admin, 'sanctum' )
			->getJson( '/api/students?faculty=Engineering' );

		$response->assertStatus( 200 );
		// Additional assertions for filtering can be added
	}

	/** @test */
	public function validation_errors_are_returned_for_invalid_student_data() {
		$invalidData = [ 
			'name'  => '', // Required field empty
			'email' => 'invalid-email', // Invalid email format
			'iin'   => '123', // Invalid IIN length
		];

		$response = $this->actingAs( $this->admin, 'sanctum' )
			->postJson( '/api/students', $invalidData );

		$response->assertStatus( 422 )
			->assertJsonValidationErrors( [ 'name', 'email', 'iin' ] );
	}
}
