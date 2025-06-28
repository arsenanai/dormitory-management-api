<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\Dormitory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DormitoryCrudTest extends TestCase {
	use RefreshDatabase;

	private $adminRoleId;

	protected function setUp(): void {
		parent::setUp();
		$this->seed();
		$this->adminRoleId = Role::where( 'name', 'admin' )->firstOrFail()->id;
	}

	public function test_admin_can_create_dormitory() {
		$token = $this->loginAsSudo();

		$payload = [ 
			'name'     => 'Alpha Dorm',
			'capacity' => 100,
		];

		$response = $this->postJson( '/api/dormitories', $payload, [ 
			'Authorization' => "Bearer $token",
		] );

		$response->assertStatus( 201 );
		$this->assertDatabaseHas( 'dormitories', [ 
			'name' => 'Alpha Dorm',
		] );
	}

	public function test_admin_can_update_dormitory() {
		$token = $this->loginAsSudo();

		$dorm = Dormitory::factory()->create();

		$response = $this->putJson( "/api/dormitories/{$dorm->id}", [ 
			'name' => 'Beta Dorm',
		], [ 
			'Authorization' => "Bearer $token",
		] );

		$response->assertStatus( 200 );
		$this->assertDatabaseHas( 'dormitories', [ 
			'id'   => $dorm->id,
			'name' => 'Beta Dorm',
		] );
	}

	public function test_admin_can_delete_dormitory() {
		$token = $this->loginAsSudo();

		$dorm = Dormitory::factory()->create();

		$response = $this->deleteJson( "/api/dormitories/{$dorm->id}", [], [ 
			'Authorization' => "Bearer $token",
		] );

		$response->assertStatus( 204 );
		$this->assertDatabaseMissing( 'dormitories', [ 
			'id' => $dorm->id,
		] );
	}

	public function test_admin_can_assign_admin_to_dormitory() {
		$token = $this->loginAsSudo();

		$dorm = Dormitory::factory()->create();
		$adminRoleId = Role::where( 'name', 'admin' )->firstOrFail()->id;
		$admin = User::factory()->create( [ 'role_id' => $adminRoleId ] );

		$response = $this->postJson( "/api/dormitories/{$dorm->id}/assign-admin", [ 
			'admin_id' => $admin->id,
		], [ 
			'Authorization' => "Bearer $token",
		] );

		$response->assertStatus( 200 );
		$this->assertDatabaseHas( 'dormitories', [ 
			'id'       => $dorm->id,
			'admin_id' => $admin->id,
		] );
	}

	public function test_create_dormitory_requires_name_and_capacity() {
		$token = $this->loginAsSudo();

		// Missing both fields
		$response = $this->postJson( '/api/dormitories', [], [ 
			'Authorization' => "Bearer $token",
		] );
		$response->assertStatus( 422 );
		$response->assertJsonValidationErrors( [ 'name', 'capacity' ] );

		// Missing capacity
		$response = $this->postJson( '/api/dormitories', [ 
			'name' => 'Dorm Without Capacity',
		], [ 
			'Authorization' => "Bearer $token",
		] );
		$response->assertStatus( 422 );
		$response->assertJsonValidationErrors( [ 'capacity' ] );

		// Invalid capacity
		$response = $this->postJson( '/api/dormitories', [ 
			'name'     => 'Dorm Invalid Capacity',
			'capacity' => 'not-a-number',
		], [ 
			'Authorization' => "Bearer $token",
		] );
		$response->assertStatus( 422 );
		$response->assertJsonValidationErrors( [ 'capacity' ] );
	}

	public function test_update_dormitory_requires_valid_fields() {
		$token = $this->loginAsSudo();
		$dorm = Dormitory::factory()->create();

		// Invalid capacity on update
		$response = $this->putJson( "/api/dormitories/{$dorm->id}", [ 
			'capacity' => 'invalid',
		], [ 
			'Authorization' => "Bearer $token",
		] );
		$response->assertStatus( 422 );
		$response->assertJsonValidationErrors( [ 'capacity' ] );

		// Name too long
		$response = $this->putJson( "/api/dormitories/{$dorm->id}", [ 
			'name' => str_repeat( 'a', 300 ),
		], [ 
			'Authorization' => "Bearer $token",
		] );
		$response->assertStatus( 422 );
		$response->assertJsonValidationErrors( [ 'name' ] );
	}

	public function test_update_admin_requires_valid_fields() {
		$token = $this->loginAsSudo();
		$admin = User::factory()->create( [ 'role_id' => $this->adminRoleId ] );

		// Invalid email and too short name
		$response = $this->putJson( "/api/admins/{$admin->id}", [ 
			'email' => 'bademail',
			'name'  => '',
		], [ 
			'Authorization' => "Bearer $token",
		] );
		$response->assertStatus( 422 );
		$response->assertJsonValidationErrors( [ 'email', 'name' ] );
	}

	private function loginAsSudo() {
		$response = $this->postJson( '/api/login', [ 
			'email'    => env( 'ADMIN_EMAIL', 'admin@email.com' ),
			'password' => env( 'ADMIN_PASSWORD', 'supersecret' ),
		] );
		return $response->json( 'token' );
	}
}