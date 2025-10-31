<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Country;
use App\Models\Region;
use App\Models\City;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Foundation\Testing\WithFaker;

class KazakhstanRegionsTest extends TestCase {
	use RefreshDatabase, WithFaker;

	protected $admin;

	protected function setUp(): void {
		parent::setUp();

		$adminRole = Role::factory()->create( [ 'name' => 'admin' ] );
		$this->admin = User::factory()->create( [ 'role_id' => $adminRole->id ] );

		// Run Kazakhstan seeder to populate data
		$this->seed( \Database\Seeders\KazakhstanSeeder::class);

		// Get the seeded Kazakhstan country
		$this->kazakhstan = Country::where( 'name', 'Kazakhstan' )->first();
	}

	#[Test]
	public function kazakhstan_regions_are_seeded() {
		$regions = Region::where( 'country_id', $this->kazakhstan->id )->get();

		$this->assertGreaterThan( 0, $regions->count() );

		// Check for specific regions
		$regionNames = $regions->pluck( 'name' )->toArray();
		$this->assertContains( 'Almaty', $regionNames );
		$this->assertContains( 'Astana', $regionNames );
		$this->assertContains( 'Shymkent', $regionNames );
	}

	#[Test]
	public function kazakhstan_cities_are_seeded() {
		$cities = City::whereHas( 'region', function ($query) {
			$query->where( 'country_id', $this->kazakhstan->id );
		} )->get();

		$this->assertGreaterThan( 0, $cities->count() );

		// Check for specific cities
		$cityNames = $cities->pluck( 'name' )->toArray();
		$this->assertContains( 'Almaty', $cityNames );
		$this->assertContains( 'Astana', $cityNames );
		$this->assertContains( 'Shymkent', $cityNames );
	}

	#[Test]
	public function admin_can_view_kazakhstan_regions() {
		$response = $this->actingAs( $this->admin )
			->getJson( "/api/regions?country_id={$this->kazakhstan->id}" );

		$response->assertStatus( 200 );
		$this->assertGreaterThan( 0, count( $response->json( 'data' ) ) );
	}

	#[Test]
	public function admin_can_view_kazakhstan_cities() {
		$region = Region::where( 'country_id', $this->kazakhstan->id )->first();

		$response = $this->actingAs( $this->admin )
			->getJson( "/api/cities?region_id={$region->id}" );

		$response->assertStatus( 200 );
		$this->assertGreaterThan( 0, count( $response->json( 'data' ) ) );
	}

	#[Test]
	public function admin_can_create_new_region() {
		$response = $this->actingAs( $this->admin )
			->postJson( "/api/regions", [ 
				'name'       => 'New Region',
				'country_id' => $this->kazakhstan->id
			] );

		$response->assertStatus( 201 );
		$this->assertDatabaseHas( 'regions', [ 
			'name'       => 'New Region',
			'country_id' => $this->kazakhstan->id
		] );
	}

	#[Test]
	public function admin_can_create_new_city() {
		$region = Region::where( 'country_id', $this->kazakhstan->id )->first();

		$response = $this->actingAs( $this->admin )
			->postJson( "/api/cities", [ 
				'name'      => 'New City',
				'region_id' => $region->id
			] );

		$response->assertStatus( 201 );
		$this->assertDatabaseHas( 'cities', [ 
			'name'      => 'New City',
			'region_id' => $region->id
		] );
	}

	#[Test]
	public function region_creation_requires_country_id() {
		$response = $this->actingAs( $this->admin )
			->postJson( "/api/regions", [ 
				'name' => 'New Region'
				// Missing country_id
			] );

		$response->assertStatus( 422 )
			->assertJsonValidationErrors( [ 'country_id' ] );
	}

	#[Test]
	public function city_creation_requires_region_id() {
		$response = $this->actingAs( $this->admin )
			->postJson( "/api/cities", [ 
				'name' => 'New City'
				// Missing region_id
			] );

		$response->assertStatus( 422 )
			->assertJsonValidationErrors( [ 'region_id' ] );
	}
}
