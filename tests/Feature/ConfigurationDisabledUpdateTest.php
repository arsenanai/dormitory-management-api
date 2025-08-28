<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Configuration;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ConfigurationDisabledUpdateTest extends TestCase {
	use RefreshDatabase;

	protected User $admin;
	protected Role $adminRole;

	protected function setUp(): void {
		parent::setUp();

		$this->adminRole = Role::create( [ 'name' => 'admin' ] );
		$this->admin = User::create( [ 
			'name'     => 'Admin',
			'email'    => 'admin@email.com',
			'password' => bcrypt( 'secret' ),
			'role_id'  => $this->adminRole->id,
			'status'   => 'active',
		] );
	}

	public function test_card_reader_update_when_disabled_allows_minimal_payload(): void {
		$resp = $this->actingAs( $this->admin )
			->putJson( '/api/configurations/card-reader', [ 
				'card_reader_enabled' => false,
			] );
		$resp->assertStatus( 200 );
	}

	public function test_onec_update_when_disabled_allows_minimal_payload(): void {
		$resp = $this->actingAs( $this->admin )
			->putJson( '/api/configurations/onec', [ 
				'onec_enabled' => false,
			] );
		$resp->assertStatus( 200 );
	}

	public function test_card_reader_update_with_frontend_payload_when_disabled(): void {
		$payload = [ 
			'card_reader_enabled'   => false,
			'card_reader_host'      => '',
			'card_reader_port'      => 1,
			'card_reader_timeout'   => 60,
			'card_reader_locations' => [],
		];

		$resp = $this->actingAs( $this->admin )
			->putJson( '/api/configurations/card-reader', $payload );
		$resp->assertStatus( 200 );
	}

	public function test_onec_update_with_frontend_payload_when_disabled(): void {
		$payload = [ 
			'onec_enabled'       => false,
			'onec_host'          => '',
			'onec_database'      => '',
			'onec_username'      => '',
			'onec_password'      => '',
			'onec_sync_interval' => 60,
		];

		$resp = $this->actingAs( $this->admin )
			->putJson( '/api/configurations/onec', $payload );
		$resp->assertStatus( 200 );
	}
}
