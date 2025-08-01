<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Hash;

class UserControllerTest extends TestCase {
	use RefreshDatabase, WithFaker;

	private User $admin;
	private User $student;
	private User $guard;

	protected function setUp(): void {
		parent::setUp();

		// Create roles
		$adminRole = Role::create( [ 'name' => 'admin' ] );
		$studentRole = Role::create( [ 'name' => 'student' ] );
		$guardRole = Role::create( [ 'name' => 'guard' ] );

		// Create test users
		$this->admin = User::factory()->create( [ 
			'role_id'  => $adminRole->id,
			'email'    => 'admin@test.com',
			'password' => bcrypt( 'password123' ),
		] );

		$this->student = User::factory()->create( [ 
			'role_id'  => $studentRole->id,
			'email'    => 'student@test.com',
			'password' => bcrypt( 'password123' ),
		] );

		$this->guard = User::factory()->create( [ 
			'role_id'  => $guardRole->id,
			'email'    => 'guard@test.com',
			'password' => bcrypt( 'password123' ),
		] );
	}

	public function test_admin_can_view_all_users() {
		$response = $this->actingAs( $this->admin )
			->getJson( '/api/users' );

		$response->assertStatus( 200 )
			->assertJsonStructure( [ 
				'data' => [ 
					'*' => [ 
						'id',
						'first_name',
						'last_name',
						'email',
						'role_id',
						'created_at',
						'updated_at',
						'role',
					]
				]
			] )
			->assertJsonCount( 3, 'data' );
	}

	public function test_admin_can_search_users() {
		$response = $this->actingAs( $this->admin )
			->getJson( '/api/users?search=student' );

		$response->assertStatus( 200 )
			->assertJsonFragment( [ 
				'email' => 'student@test.com',
			] );
	}

	public function test_admin_can_filter_users_by_role() {
		$response = $this->actingAs( $this->admin )
			->getJson( '/api/users?role=student' );

		$response->assertStatus( 200 )
			->assertJsonCount( 1, 'data' )
			->assertJsonFragment( [ 
				'email' => 'student@test.com',
			] );
	}

	public function test_admin_can_create_user() {
		$userData = [ 
			'first_name'            => 'New',
			'last_name'             => 'User',
			'email'                 => 'newuser@test.com',
			'password'              => 'password123',
			'password_confirmation' => 'password123',
			'role_id'               => $this->student->role_id,
			'phone'                 => '+1234567890',
			'date_of_birth'         => '1995-05-15',
		];

		$response = $this->actingAs( $this->admin )
			->postJson( '/api/users', $userData );

		$response->assertStatus( 201 )
			->assertJsonFragment( [ 
				'first_name'    => 'New',
				'last_name'     => 'User',
				'email'         => 'newuser@test.com',
				'phone_numbers' => [ '+1234567890' ],
			] );

		$this->assertDatabaseHas( 'users', [ 
			'email'         => 'newuser@test.com',
			'first_name'    => 'New',
			'last_name'     => 'User',
			'phone_numbers' => json_encode( [ '+1234567890' ] ),
		] );
	}

	public function test_admin_can_create_user_with_student_fields() {
		$userData = [ 
			'first_name'            => 'John',
			'last_name'             => 'Doe',
			'email'                 => 'john.doe@test.com',
			'password'              => 'password123',
			'password_confirmation' => 'password123',
			'role_id'               => $this->student->role_id,
			'phone'                 => '+1234567890',
			'date_of_birth'         => '1995-05-15',
			'student_id'            => 'STU001',
			'blood_type'            => 'O+',
			'emergency_contact'     => 'Jane Doe - +0987654321',
			'course'                => 'Computer Science',
			'year_of_study'         => 2,
			'violations'            => 'None',
		];

		$userData['iin'] = '123456789012';

		$response = $this->actingAs( $this->admin )
			->postJson( '/api/users', $userData );

		$response->assertStatus( 201 )
			->assertJsonStructure( [
				'student_profile' => [
					'student_id',
					'blood_type',
					'course',
					'year_of_study'
				]
			] )
			->assertJsonPath( 'student_profile.student_id', 'STU001' )
			->assertJsonPath( 'student_profile.blood_type', 'O+' )
			->assertJsonPath( 'student_profile.course', 'Computer Science' )
			->assertJsonPath( 'student_profile.year_of_study', 2 );

		// Assert in student_profiles, not users
		$this->assertDatabaseHas( 'student_profiles', [ 
			'student_id'    => 'STU001',
			'blood_type'    => 'O+',
			'course'        => 'Computer Science',
			'year_of_study' => 2,
		] );
	}

	public function test_create_user_validation() {
		$response = $this->actingAs( $this->admin )
			->postJson( '/api/users', [] );

		$response->assertStatus( 422 )
			->assertJsonValidationErrors( [ 
				'first_name',
				'last_name',
				'email',
				'password',
				'role_id',
			] );
	}

	public function test_create_user_with_duplicate_email() {
		$userData = [ 
			'first_name'            => 'Duplicate',
			'last_name'             => 'User',
			'email'                 => 'student@test.com', // Already exists
			'password'              => 'password123',
			'password_confirmation' => 'password123',
			'role_id'               => $this->student->role_id,
		];

		$response = $this->actingAs( $this->admin )
			->postJson( '/api/users', $userData );

		$response->assertStatus( 422 )
			->assertJsonValidationErrors( [ 'email' ] );
	}

	public function test_admin_can_view_specific_user() {
		$response = $this->actingAs( $this->admin )
			->getJson( "/api/users/{$this->student->id}" );

		$response->assertStatus( 200 )
			->assertJsonFragment( [ 
				'id'         => $this->student->id,
				'email'      => $this->student->email,
				'first_name' => $this->student->first_name,
				'last_name'  => $this->student->last_name,
			] );
	}

	public function test_admin_can_update_user() {
		$updateData = [ 
			'first_name' => 'Updated',
			'last_name'  => 'Student',
			'phone'      => '+9876543210',
			'blood_type' => 'AB+',
			'course'     => 'Updated Course',
		];

		$updateData['iin'] = '123456789012';

		$response = $this->actingAs( $this->admin )
			->putJson( "/api/users/{$this->student->id}", $updateData );

		$response->assertStatus( 200 )
			->assertJsonPath( 'first_name', 'Updated' )
			->assertJsonPath( 'last_name', 'Student' )
			->assertJsonPath( 'phone_numbers', [ '+9876543210' ] )
			->assertJsonPath( 'student_profile.blood_type', 'AB+' )
			->assertJsonPath( 'student_profile.course', 'Updated Course' );

		// Role-specific fields in student_profiles
		$this->assertDatabaseHas( 'student_profiles', [ 
			'user_id'    => $this->student->id,
			'blood_type' => 'AB+',
			'course'     => 'Updated Course',
		] );
	}

	public function test_admin_can_update_user_password() {
		$updateData = [ 
			'password'              => 'newpassword123',
			'password_confirmation' => 'newpassword123',
		];

		$response = $this->actingAs( $this->admin )
			->putJson( "/api/users/{$this->student->id}", $updateData );

		$response->assertStatus( 200 );

		$this->student->refresh();
		$this->assertTrue( Hash::check( 'newpassword123', $this->student->password ) );
	}

	public function test_admin_can_delete_user() {
		$userToDelete = User::factory()->create( [ 
			'role_id' => $this->student->role_id,
			'email'   => 'delete@test.com',
		] );

		$response = $this->actingAs( $this->admin )
			->deleteJson( "/api/users/{$userToDelete->id}" );

		$response->assertStatus( 200 )
			->assertJson( [ 'message' => 'User deleted successfully' ] );

		$this->assertSoftDeleted( 'users', [ 
			'id' => $userToDelete->id,
		] );
	}

	public function test_user_can_view_own_profile() {
		$response = $this->actingAs( $this->student )
			->getJson( '/api/users/profile' );

		$response->assertStatus( 200 )
			->assertJsonFragment( [ 
				'id'         => $this->student->id,
				'email'      => $this->student->email,
				'first_name' => $this->student->first_name,
				'last_name'  => $this->student->last_name,
			] );
	}

	public function test_user_can_update_own_profile() {
		$updateData = [ 
			'first_name'        => 'Updated',
			'last_name'         => 'Name',
			'phone'             => '+1111111111',
			'emergency_contact' => 'Updated Emergency Contact',
		];

		$updateData['iin'] = '123456789012';

		$response = $this->actingAs( $this->student )
			->putJson( '/api/users/profile', $updateData );

		$response->assertStatus( 200 )
			->assertJsonPath( 'first_name', 'Updated' )
			->assertJsonPath( 'last_name', 'Name' )
			->assertJsonPath( 'phone_numbers', [ '+1111111111' ] )
			->assertJsonPath( 'student_profile.emergency_contact_name', 'Updated Emergency Contact' );

		// Role-specific field in student_profiles
		$this->assertDatabaseHas( 'student_profiles', [ 
			'user_id'                => $this->student->id,
			'emergency_contact_name' => 'Updated Emergency Contact',
		] );
	}

	public function test_user_can_change_own_password() {
		$updateData = [ 
			'current_password'      => 'password123',
			'password'              => 'newpassword123',
			'password_confirmation' => 'newpassword123',
		];

		$response = $this->actingAs( $this->student )
			->putJson( '/api/users/change-password', $updateData );

		$response->assertStatus( 200 )
			->assertJson( [ 'message' => 'Password updated successfully' ] );

		$this->student->refresh();
		$this->assertTrue( Hash::check( 'newpassword123', $this->student->password ) );
	}

	public function test_user_cannot_change_password_with_wrong_current_password() {
		$updateData = [ 
			'current_password'      => 'wrongpassword',
			'password'              => 'newpassword123',
			'password_confirmation' => 'newpassword123',
		];

		$response = $this->actingAs( $this->student )
			->putJson( '/api/users/change-password', $updateData );

		$response->assertStatus( 422 )
			->assertJsonValidationErrors( [ 'current_password' ] );
	}

	public function test_user_cannot_update_role_in_profile() {
		$updateData = [ 
			'role_id'    => $this->admin->role_id, // Try to become admin
			'first_name' => 'Hacker',
		];

		$response = $this->actingAs( $this->student )
			->putJson( '/api/users/profile', $updateData );

		$response->assertStatus( 200 );

		$this->student->refresh();
		$this->assertNotEquals( $this->admin->role_id, $this->student->role_id );
		$this->assertEquals( 'Hacker', $this->student->first_name );
	}

	public function test_student_cannot_access_all_users() {
		$response = $this->actingAs( $this->student )
			->getJson( '/api/users' );

		$response->assertStatus( 403 );
	}

	public function test_student_cannot_view_other_user_details() {
		$response = $this->actingAs( $this->student )
			->getJson( "/api/users/{$this->guard->id}" );

		$response->assertStatus( 403 );
	}

	public function test_student_cannot_create_user() {
		$userData = [ 
			'first_name'            => 'New',
			'last_name'             => 'User',
			'email'                 => 'newuser@test.com',
			'password'              => 'password123',
			'password_confirmation' => 'password123',
			'role_id'               => $this->student->role_id,
		];

		$response = $this->actingAs( $this->student )
			->postJson( '/api/users', $userData );

		$response->assertStatus( 403 );
	}

	public function test_student_cannot_update_other_user() {
		$updateData = [ 
			'first_name' => 'Hacked',
			'last_name'  => 'User',
		];

		$response = $this->actingAs( $this->student )
			->putJson( "/api/users/{$this->guard->id}", $updateData );

		$response->assertStatus( 403 );
	}

	public function test_student_cannot_delete_user() {
		$response = $this->actingAs( $this->student )
			->deleteJson( "/api/users/{$this->guard->id}" );

		$response->assertStatus( 403 );
	}

	public function test_unauthenticated_user_cannot_access_users() {
		$response = $this->getJson( '/api/users' );
		$response->assertStatus( 401 );

		$response = $this->getJson( '/api/users/profile' );
		$response->assertStatus( 401 );
	}
}
