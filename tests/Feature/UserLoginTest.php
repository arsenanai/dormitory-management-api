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

		$response = $this->postJson( '/api/login', [ 
			'email'    => 'admin@email.com',
			'password' => 'supersecret',
		] );

		$response->assertStatus( 200 )->assertJsonStructure( [ 'user', 'token' ] );
	}
}
