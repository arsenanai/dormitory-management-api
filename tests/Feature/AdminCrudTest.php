<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCrudTest extends TestCase {
	private $adminRoleId;

	use RefreshDatabase;

	protected function setUp(): void {
		parent::setUp();
		$this->seed();
		$this->adminRoleId = Role::where( 'name', 'admin' )->firstOrFail()->id;
	}

	public function test_sudo_can_list_admins() {
		$token = $this->loginAsSudo();

		// Create some admins
		User::factory()->count( 2 )->create( [ 'role_id' => $this->adminRoleId ] );
		$response = $this->getJson( '/api/admins', [ 
			'Authorization' => "Bearer $token",
		] );

		$response->assertStatus( 200 );
		$response->assertJsonStructure( [ '*' => [ 'id', 'name', 'email', 'role_id' ] ] );
		$this->assertGreaterThanOrEqual( 2, count( $response->json() ) );
	}

	public function test_sudo_can_create_admin() {
		$token = $this->loginAsSudo();

		$payload = [ 
			'name'            => 'Test Admin',
			'email'           => 'testadmin@example.com',
			'password'        => 'password',
			'role_id'         => $this->adminRoleId,
			'iin'             => '123456789012',
			'faculty'         => 'Engineering',
			'specialist'      => 'Manager',
			'enrollment_year' => '2020',
			'gender'          => 'male',
		];

		$response = $this->postJson( '/api/admins', $payload, [ 
			'Authorization' => "Bearer $token",
		] );

		$response->assertStatus( 201 );
		$this->assertDatabaseHas( 'users', [ 
			'email'   => 'testadmin@example.com',
			'role_id' => $this->adminRoleId,
		] );
		// Assert role-specific fields in admin_profiles
		$this->assertDatabaseHas( 'admin_profiles', [
			// Example: if payload includes these fields, check them
			// 'faculty'         => 'Engineering',
			// 'specialist'      => 'Manager',
			// 'enrollment_year' => '2020',
			// 'gender'          => 'male',
		] );
	}

	public function test_sudo_can_update_admin() {
		$token = $this->loginAsSudo();

		$admin = User::factory()->create( [ 
			'role_id' => $this->adminRoleId,
			'email'   => 'updateadmin@example.com',
			'name'    => 'Old Name',
		] );

		$response = $this->putJson( "/api/admins/{$admin->id}", [ 
			'name' => 'Updated Admin',
		], [ 
			'Authorization' => "Bearer $token",
		] );

		$response->assertStatus( 200 );
		$this->assertDatabaseHas( 'users', [ 
			'id'   => $admin->id,
			'name' => 'Updated Admin',
		] );
	}

	public function test_sudo_can_delete_admin() {
		$token = $this->loginAsSudo();

		$admin = User::factory()->create( [ 
			'role_id' => $this->adminRoleId,
			'email'   => 'deleteadmin@example.com',
		] );

		$response = $this->deleteJson( "/api/admins/{$admin->id}", [], [ 
			'Authorization' => "Bearer $token",
		] );

		$response->assertStatus( 200 );
		$this->assertDatabaseMissing( 'users', [ 
			'id' => $admin->id,
		] );
	}

	private function loginAsSudo() {
		$response = $this->postJson( '/api/login', [ 
			'email'    => 'sudo@email.com',
			'password' => 'supersecret',
		] );

		return $response->json( 'token' );
	}

	public function test_create_admin_requires_valid_email_and_gender() {
		$token = $this->loginAsSudo();

		$payload = [ 
			'name'     => 'Test Admin',
			'email'    => 'not-an-email',
			'password' => 'short',
			'role_id'  => 999, // not admin role
			'gender'   => 'other',
		];

		$response = $this->postJson( '/api/admins', $payload, [ 
			'Authorization' => "Bearer $token",
		] );
		$response->assertStatus( 422 );
		$response->assertJsonValidationErrors( [ 
			'email', 'password', 'gender'
		] );
	}

	public function test_create_admin_requires_required_fields() {
		$token = $this->loginAsSudo();

		// Missing all required fields
		$response = $this->postJson( '/api/admins', [], [ 
			'Authorization' => "Bearer $token",
		] );
		$response->assertStatus( 422 );
		$response->assertJsonValidationErrors( [ 
			'name', 'email', 'password'
		] );

		// Invalid email and short password
		$response = $this->postJson( '/api/admins', [ 
			'name'            => 'A',
			'email'           => 'not-an-email',
			'password'        => '123',
			'role_id'         => 'not-a-number',
			'iin'             => '',
			'faculty'         => '',
			'specialist'      => '',
			'enrollment_year' => '',
			'gender'          => '',
		], [ 
			'Authorization' => "Bearer $token",
		] );
		$response->assertStatus( 422 );
		$response->assertJsonValidationErrors( [ 
			'email', 'password'
		] );
	}

	public function test_update_admin_requires_valid_fields() {
		$token = $this->loginAsSudo();
		$admin = User::factory()->create( [ 'role_id' => $this->adminRoleId ] );

		$response = $this->putJson( "/api/admins/{$admin->id}", [ 
			'email'  => 'bademail',
			'gender' => 'other',
		], [ 
			'Authorization' => "Bearer $token",
		] );
		$response->assertStatus( 422 );
		$response->assertJsonValidationErrors( [ 'email', 'gender' ] );
	}
}