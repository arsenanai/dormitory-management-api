<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Configuration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

class KaspiSettingsTest extends TestCase {
	use RefreshDatabase, WithFaker;

	protected $admin;
	protected $adminRole;

	protected function setUp(): void {
		parent::setUp();

		// Create admin role
		$this->adminRole = Role::create( [ 'name' => 'admin' ] );

		// Create admin user
		$this->admin = User::create( [ 
			'name'     => 'Admin User',
			'email'    => 'admin@email.com',
			'password' => bcrypt( 'password' ),
			'role_id'  => $this->adminRole->id,
			'status'   => 'active'
		] );
	}

	/** @test */
	public function it_can_get_kaspi_settings() {
		// Given: Kaspi settings exist in configurations
		Configuration::create( [ 
			'key'   => 'kaspi_enabled',
			'value' => 'true'
		] );
		Configuration::create( [ 
			'key'   => 'kaspi_api_key',
			'value' => 'test_api_key_123'
		] );
		Configuration::create( [ 
			'key'   => 'kaspi_merchant_id',
			'value' => 'merchant_456'
		] );
		Configuration::create( [ 
			'key'   => 'kaspi_webhook_url',
			'value' => 'https://webhook.example.com/kaspi'
		] );

		// When: Admin requests Kaspi settings
		$response = $this->actingAs( $this->admin )
			->getJson( '/api/configurations/kaspi' );

		// Then: Should return Kaspi settings
		$response->assertStatus( 200 )
			->assertJson( [ 
				'kaspi_enabled'     => true,
				'kaspi_api_key'     => 'test_api_key_123',
				'kaspi_merchant_id' => 'merchant_456',
				'kaspi_webhook_url' => 'https://webhook.example.com/kaspi'
			] );
	}

	/** @test */
	public function it_returns_default_kaspi_settings_when_none_exist() {
		// When: Admin requests Kaspi settings (none exist)
		$response = $this->actingAs( $this->admin )
			->getJson( '/api/configurations/kaspi' );

		// Then: Should return default settings
		$response->assertStatus( 200 )
			->assertJson( [ 
				'kaspi_enabled'     => false,
				'kaspi_api_key'     => null,
				'kaspi_merchant_id' => null,
				'kaspi_webhook_url' => null
			] );
	}

	/** @test */
	public function it_can_update_kaspi_settings() {
		// Given: Valid Kaspi settings data
		$kaspiData = [ 
			'kaspi_enabled'     => true,
			'kaspi_api_key'     => 'new_api_key_789',
			'kaspi_merchant_id' => 'new_merchant_123',
			'kaspi_webhook_url' => 'https://new-webhook.example.com/kaspi'
		];

		// When: Admin updates Kaspi settings
		$response = $this->actingAs( $this->admin )
			->putJson( '/api/configurations/kaspi', $kaspiData );

		// Then: Should return success and updated settings
		$response->assertStatus( 200 )
			->assertJson( $kaspiData );

		// And: Settings should be saved to database
		$this->assertDatabaseHas( 'configurations', [ 
			'key'   => 'kaspi_enabled',
			'value' => 'true'
		] );
		$this->assertDatabaseHas( 'configurations', [ 
			'key'   => 'kaspi_api_key',
			'value' => 'new_api_key_789'
		] );
		$this->assertDatabaseHas( 'configurations', [ 
			'key'   => 'kaspi_merchant_id',
			'value' => 'new_merchant_123'
		] );
		$this->assertDatabaseHas( 'configurations', [ 
			'key'   => 'kaspi_webhook_url',
			'value' => 'https://new-webhook.example.com/kaspi'
		] );
	}

	/** @test */
	public function it_validates_kaspi_settings_when_enabled() {
		// Given: Kaspi is enabled but required fields are missing
		$kaspiData = [ 
			'kaspi_enabled'     => true,
			'kaspi_api_key'     => '', // Missing
			'kaspi_merchant_id' => '', // Missing
			'kaspi_webhook_url' => '' // Missing
		];

		// When: Admin tries to update with invalid data
		$response = $this->actingAs( $this->admin )
			->putJson( '/api/configurations/kaspi', $kaspiData );

		// Then: Should return validation errors
		$response->assertStatus( 422 )
			->assertJsonValidationErrors( [ 
				'kaspi_api_key',
				'kaspi_merchant_id',
				'kaspi_webhook_url'
			] );
	}

	/** @test */
	public function it_allows_empty_fields_when_kaspi_is_disabled() {
		// Given: Kaspi is disabled with empty fields
		$kaspiData = [ 
			'kaspi_enabled'     => false,
			'kaspi_api_key'     => '',
			'kaspi_merchant_id' => '',
			'kaspi_webhook_url' => ''
		];

		// When: Admin updates with disabled Kaspi
		$response = $this->actingAs( $this->admin )
			->putJson( '/api/configurations/kaspi', $kaspiData );

		// Then: Should succeed
		$response->assertStatus( 200 )
			->assertJson( [ 
				'kaspi_enabled'     => false,
				'kaspi_api_key'     => null,
				'kaspi_merchant_id' => null,
				'kaspi_webhook_url' => null
			] );
	}

	/** @test */
	public function it_validates_api_key_format() {
		// Given: Kaspi is enabled with invalid API key
		$kaspiData = [ 
			'kaspi_enabled'     => true,
			'kaspi_api_key'     => 'invalid key with spaces', // Invalid format
			'kaspi_merchant_id' => 'merchant_123',
			'kaspi_webhook_url' => 'https://webhook.example.com/kaspi'
		];

		// When: Admin tries to update with invalid API key
		$response = $this->actingAs( $this->admin )
			->putJson( '/api/configurations/kaspi', $kaspiData );

		// Then: Should return validation error for API key
		$response->assertStatus( 422 )
			->assertJsonValidationErrors( [ 'kaspi_api_key' ] );
	}

	/** @test */
	public function it_validates_webhook_url_format() {
		// Given: Kaspi is enabled with invalid webhook URL
		$kaspiData = [ 
			'kaspi_enabled'     => true,
			'kaspi_api_key'     => 'valid_api_key_123',
			'kaspi_merchant_id' => 'merchant_123',
			'kaspi_webhook_url' => 'not-a-valid-url' // Invalid URL
		];

		// When: Admin tries to update with invalid webhook URL
		$response = $this->actingAs( $this->admin )
			->putJson( '/api/configurations/kaspi', $kaspiData );

		// Then: Should return validation error for webhook URL
		$response->assertStatus( 422 )
			->assertJsonValidationErrors( [ 'kaspi_webhook_url' ] );
	}

	/** @test */
	public function it_requires_authentication() {
		// When: Unauthenticated user tries to get Kaspi settings
		$response = $this->getJson( '/api/configurations/kaspi' );

		// Then: Should return unauthorized
		$response->assertStatus( 401 );
	}

	/** @test */
	public function it_requires_admin_role() {
		// Given: Regular user (non-admin)
		$userRole = Role::create( [ 'name' => 'user' ] );
		$user = User::create( [ 
			'name'     => 'Regular User',
			'email'    => 'user@example.com',
			'password' => bcrypt( 'password' ),
			'role_id'  => $userRole->id,
			'status'   => 'active'
		] );

		// When: Regular user tries to get Kaspi settings
		$response = $this->actingAs( $user )
			->getJson( '/api/configurations/kaspi' );

		// Then: Should return forbidden
		$response->assertStatus( 403 );
	}
}
