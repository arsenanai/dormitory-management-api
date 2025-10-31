<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class UserLoginTest extends TestCase {
	use RefreshDatabase;

	protected function setUp(): void {
		parent::setUp();
		$this->seed();
	}

	public function test_example(): void {
		// Ensure the admin user is associated with a dormitory, as required by the login logic.
		$adminUser = User::where('email', 'admin@email.com')->first();
		if ($adminUser && !$adminUser->adminDormitory) {
			$dormitory = \App\Models\Dormitory::factory()->create(['admin_id' => $adminUser->id]);
			\App\Models\AdminProfile::factory()->create(['user_id' => $adminUser->id, 'dormitory_id' => $dormitory->id]);
		}

		$response = $this->postJson( '/api/login', [ 
			'email'    => 'admin@email.com',
			'password' => 'supersecret',
		] );

		$response->assertStatus( 200 )->assertJsonStructure( [ 'user', 'token' ] );
	}
}
