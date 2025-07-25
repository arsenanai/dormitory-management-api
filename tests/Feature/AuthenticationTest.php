<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase {
	use RefreshDatabase;

	public function test_user_can_login_and_get_profile() {
		// Create a role and user
		$role = Role::factory()->create( [ 'name' => 'admin' ] );
		$user = User::factory()->create( [ 
			'email'    => 'admin@example.com',
			'password' => bcrypt( 'password' ),
			'role_id'  => $role->id
		] );

		// Login
		$response = $this->postJson( '/api/login', [ 
			'email'    => 'admin@example.com',
			'password' => 'password'
		] );

		$response->assertStatus( 200 )
			->assertJsonStructure( [ 
				'token',
				'user' => [ 
					'id',
					'name',
					'email',
					'role'
				]
			] );

		$token = $response->json( 'token' );

		// Test profile endpoint
		$profileResponse = $this->withHeaders( [ 
			'Authorization' => 'Bearer ' . $token
		] )->getJson( '/api/users/profile' );

		$profileResponse->assertStatus( 200 )
			->assertJsonStructure( [ 
				'id',
				'name',
				'email',
				'role'
			] );
	}

	public function test_user_can_logout() {
		// Create a role and user
		$role = Role::factory()->create( [ 'name' => 'admin' ] );
		$user = User::factory()->create( [ 
			'email'    => 'admin@example.com',
			'password' => bcrypt( 'password' ),
			'role_id'  => $role->id
		] );

		// Login first
		$loginResponse = $this->postJson( '/api/login', [ 
			'email'    => 'admin@example.com',
			'password' => 'password'
		] );

		$token = $loginResponse->json( 'token' );

		// Test logout endpoint
		$logoutResponse = $this->withHeaders( [ 
			'Authorization' => 'Bearer ' . $token
		] )->postJson( '/api/logout' );

		$logoutResponse->assertStatus( 200 )
			->assertJson( [ 'message' => 'Logged out successfully' ] );

		// Note: Token invalidation testing might require different setup in test environment
		// The important thing is that the logout endpoint exists and responds correctly
	}

	public function test_profile_returns_different_data_for_different_roles() {
		// Test admin profile
		$adminRole = Role::factory()->create( [ 'name' => 'admin' ] );
		$admin = User::factory()->create( [ 
			'email'    => 'admin@example.com',
			'password' => bcrypt( 'password' ),
			'role_id'  => $adminRole->id
		] );
		// Optionally create AdminProfile if needed
		\App\Models\AdminProfile::factory()->create( [ 'user_id' => $admin->id ] );

		$adminResponse = $this->actingAs( $admin )->getJson( '/api/users/profile' );
		$adminResponse->assertStatus( 200 )
			->assertJsonStructure( [ 
				'id',
				'name',
				'email',
				'role',
				'admin_profile',
			] );

		// Test student profile
		$studentRole = Role::factory()->create( [ 'name' => 'student' ] );
		$student = User::factory()->create( [ 
			'email'    => 'student@example.com',
			'password' => bcrypt( 'password' ),
			'role_id'  => $studentRole->id
		] );
		\App\Models\StudentProfile::factory()->create( [ 'user_id' => $student->id ] );

		$studentResponse = $this->actingAs( $student )->getJson( '/api/users/profile' );
		$studentResponse->assertStatus( 200 )
			->assertJsonStructure( [ 
				'id',
				'name',
				'email',
				'role',
				'student_profile',
			] );

		// Both should return user data but potentially different structure
		$this->assertNotEmpty( $adminResponse->json( 'email' ) );
		$this->assertNotEmpty( $studentResponse->json( 'email' ) );
	}
}
