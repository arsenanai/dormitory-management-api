<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentManualApprovalTest extends TestCase {
	use RefreshDatabase;

	protected function setUp(): void {
		parent::setUp();
		$this->seed();
	}

	public function test_student_cannot_login_while_pending() {
		$studentRole = Role::where( 'name', 'student' )->first();
		$student = User::factory()->create( [ 
			'email'    => 'pending@student.com',
			'password' => bcrypt( 'password123' ),
			'role_id'  => $studentRole->id,
			'status'   => 'pending',
		] );

		$response = $this->postJson( '/api/login', [ 
			'email'    => 'pending@student.com',
			'password' => 'password123',
		] );

		$response->assertStatus( 401 );
		$response->assertJson( [ 'message' => 'auth.not_approved' ] );
	}

	public function test_admin_can_approve_student() {
		$studentRole = Role::where( 'name', 'student' )->first();
		$student = User::factory()->create( [ 
			'email'    => 'approve@student.com',
			'password' => bcrypt( 'password123' ),
			'role_id'  => $studentRole->id,
			'status'   => 'pending',
		] );

		// Create an admin or sudo user and authenticate
		$adminRole = Role::where( 'name', 'sudo' )->first();
		$admin = User::factory()->create( [ 
			'role_id' => $adminRole->id,
			'status'  => 'active',
		] );
		$token = $admin->createToken( 'test-token' )->plainTextToken;

		$response = $this->withHeader( 'Authorization', 'Bearer ' . $token )
			->patchJson( "/api/students/{$student->id}/approve" );

		$response->assertStatus( 200 );
		$this->assertDatabaseHas( 'users', [ 
			'id'     => $student->id,
			'status' => 'active',
		] );
	}

	public function test_approved_student_can_login() {
		$studentRole = Role::where( 'name', 'student' )->first();
		$student = User::factory()->create( [ 
			'email'    => 'active@student.com',
			'password' => bcrypt( 'password123' ),
			'role_id'  => $studentRole->id,
			'status'   => 'active',
		] );

		$response = $this->postJson( '/api/login', [ 
			'email'    => 'active@student.com',
			'password' => 'password123',
		] );

		$response->assertStatus( 200 );
		$response->assertJsonStructure( [ 'user', 'token' ] );
	}
}