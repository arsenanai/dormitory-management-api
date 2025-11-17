<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\RoomType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RoomTypeCrudTest extends TestCase {
	use RefreshDatabase;

	protected function setUp(): void {
		parent::setUp();
		// Create necessary roles and a sudo user for authentication,
		// instead of running the full seeder which can cause data conflicts.
		$sudoRole = Role::firstOrCreate(['name' => 'sudo']);
		User::factory()->create([
			'email' => 'sudo@email.com',
			'password' => bcrypt('supersecret'),
			'role_id' => $sudoRole->id,
		]);
		// Seed the room types needed for the 'api_returns_exactly_two_room_types' test
		$this->seed(\Database\Seeders\RoomTypeSeeder::class);
	}

	public function test_can_create_standard_room_type() {
		Storage::fake( 'public' );
		$token = $this->loginAsSudo();

		$payload = [
			'name'          => 'standard_new',
			'capacity'      => 2,
			'daily_rate'    => 150.00,
			'semester_rate' => 20000.00,
			'minimap'       => UploadedFile::fake()->image( 'minimap.jpg' ),
			'beds'          => [
				[ 'x' => 10, 'y' => 20, 'width' => 30, 'height' => 40 ],
				[ 'x' => 50, 'y' => 60, 'width' => 30, 'height' => 40 ],
			],
			'photos'   => [ 
				UploadedFile::fake()->image( 'photo1.jpg' ),
				UploadedFile::fake()->image( 'photo2.jpg' ),
			],
		];

		$response = $this->postJson( '/api/room-types', [
			'name'          => $payload['name'],
			'capacity'      => $payload['capacity'],
			'daily_rate'    => $payload['daily_rate'],
			'semester_rate' => $payload['semester_rate'],
			'minimap'       => $payload['minimap'],
			'beds'          => json_encode( $payload['beds'] ),
			'photos'        => $payload['photos'],
		], [ 
			'Authorization' => "Bearer $token",
		] );

		$response->assertStatus( 201 );
		$this->assertDatabaseHas( 'room_types', [ 
			'name'     => 'standard_new',
			'capacity' => 2,
			'daily_rate'    => 150.00,
			'semester_rate' => 20000.00,
		] );
		Storage::disk( 'public' )->assertExists( 'minimaps/' . $payload['minimap']->hashName() );
		Storage::disk( 'public' )->assertExists( 'photos/' . $payload['photos'][0]->hashName() );
		Storage::disk( 'public' )->assertExists( 'photos/' . $payload['photos'][1]->hashName() );
	}

	public function test_can_create_lux_room_type() {
		Storage::fake( 'public' );
		$token = $this->loginAsSudo();

		$payload = [
			'name'          => 'lux_new',
			'capacity'      => 1,
			'daily_rate'    => 300.00,
			'semester_rate' => 40000.00,
			'minimap'       => UploadedFile::fake()->image( 'minimap.jpg' ),
			'beds'          => [
				[ 'x' => 10, 'y' => 20, 'width' => 30, 'height' => 40 ],
			],
			'photos'   => [ 
				UploadedFile::fake()->image( 'photo1.jpg' ),
			],
		];

		$response = $this->postJson( '/api/room-types', [
			'name'          => $payload['name'],
			'capacity'      => $payload['capacity'],
			'daily_rate'    => $payload['daily_rate'],
			'semester_rate' => $payload['semester_rate'],
			'minimap'       => $payload['minimap'],
			'beds'          => json_encode( $payload['beds'] ),
			'photos'        => $payload['photos'],
		], [ 
			'Authorization' => "Bearer $token",
		] );

		$response->assertStatus( 201 );
		$this->assertDatabaseHas( 'room_types', [ 
			'name'     => 'lux_new',
			'capacity' => 1,
			'daily_rate'    => 300.00,
		] );
	}

	public function test_cannot_create_room_type_without_required_fields() {
		$token = $this->loginAsSudo();

		$response = $this->postJson( '/api/room-types', [ 
			'minimap' => UploadedFile::fake()->image( 'minimap.jpg' ),
		], [ 
			'Authorization' => "Bearer $token",
		] );

		$response->assertStatus( 422 );
		$response->assertJsonValidationErrors( [ 'name', 'capacity', 'daily_rate', 'semester_rate' ] );
	}

	public function test_can_update_room_type() {
		$token = $this->loginAsSudo();
		$roomType = RoomType::factory()->create( [ 
			'name'          => 'standard-to-update',
			'capacity'      => 2,
			'daily_rate'    => 150.00,
			'semester_rate' => 20000.00,
		] );

		$response = $this->putJson( "/api/room-types/{$roomType->id}", [
			'name'          => 'lux-updated',
			'capacity'      => 1,
			'daily_rate'    => 300.00,
			'semester_rate' => 50000.00,
			'beds'          => json_encode( [
				[ 'x' => 1, 'y' => 2, 'width' => 3, 'height' => 4 ],
			] ),
		], [ 
			'Authorization' => "Bearer $token",
		] );

		$response->assertStatus( 200 );
		$this->assertDatabaseHas( 'room_types', [ 
			'id'       => $roomType->id,
			'name'     => 'lux-updated',
			'capacity' => 1,
			'daily_rate'    => 300.00,
		] );
	}

	public function test_can_update_room_type_photos() {
		Storage::fake( 'public' );
		$token = $this->loginAsSudo();
		$roomType = RoomType::factory()->create( [ 
			'name'          => 'standard-for-photos',
			'capacity'      => 2,
			'daily_rate'    => 150.00,
			'semester_rate' => 20000.00,
		] );

		$newPhotos = [ 
			UploadedFile::fake()->image( 'newphoto1.jpg' ),
			UploadedFile::fake()->image( 'newphoto2.jpg' ),
		];

		$response = $this->putJson( "/api/room-types/{$roomType->id}", [ 
			'photos' => $newPhotos,
		], [ 
			'Authorization' => "Bearer $token",
		] );

		$response->assertStatus( 200 );
		Storage::disk( 'public' )->assertExists( 'photos/' . $newPhotos[0]->hashName() );
		Storage::disk( 'public' )->assertExists( 'photos/' . $newPhotos[1]->hashName() );
	}

	public function test_can_delete_room_type() {
		$token = $this->loginAsSudo();
		$roomType = RoomType::factory()->create( [ 
			'name'          => 'to-be-deleted',
			'capacity'      => 2,
			'daily_rate'    => 150.00,
			'semester_rate' => 20000.00,
		] );

		$response = $this->deleteJson( "/api/room-types/{$roomType->id}", [], [ 
			'Authorization' => "Bearer $token",
		] );

		$response->assertStatus( 200 );
		$this->assertDatabaseMissing( 'room_types', [ 
			'id' => $roomType->id,
		] );
	}

	public function test_can_list_room_types() {
		$token = $this->loginAsSudo();

		// The seeder in setUp() already creates 'standard' and 'lux'

		$response = $this->getJson( '/api/room-types', [ 
			'Authorization' => "Bearer $token",
		] );

		$response->assertStatus( 200 );
		$response->assertJsonFragment( [
			'name'     => 'standard',
			'capacity' => 2,
		] );
		$response->assertJsonFragment( [
			'name'     => 'lux',
			'capacity' => 1,
		] );
	}

	public function test_api_returns_exactly_two_room_types() {
		$token = $this->loginAsSudo();

		$response = $this->getJson( '/api/room-types', [ 
			'Authorization' => "Bearer $token",
		] );

		$response->assertStatus( 200 );
		$roomTypes = $response->json();

		// Should have exactly 2 room types
		$this->assertCount( 2, $roomTypes );

		// Find standard and lux room types
		$standard = collect( $roomTypes )->firstWhere( 'name', 'standard' );
		$lux = collect( $roomTypes )->firstWhere( 'name', 'lux' );

		// Verify standard room type
		$this->assertNotNull( $standard );
		$this->assertEquals( 2, $standard['capacity'] );
		$this->assertArrayHasKey('daily_rate', $standard);

		// Verify lux room type
		$this->assertNotNull( $lux );
		$this->assertEquals( 1, $lux['capacity'] );
		$this->assertArrayHasKey('daily_rate', $lux);
	}

	public function test_room_types_have_consistent_data_structure() {
		$token = $this->loginAsSudo();

		$response = $this->getJson( '/api/room-types', [ 
			'Authorization' => "Bearer $token",
		] );

		$response->assertStatus( 200 );
		$roomTypes = $response->json();

		foreach ( $roomTypes as $roomType ) {
			// Check required fields exist
			$this->assertArrayHasKey( 'id', $roomType );
			$this->assertArrayHasKey( 'name', $roomType );
			$this->assertArrayHasKey( 'capacity', $roomType );
			$this->assertArrayHasKey( 'daily_rate', $roomType );
			$this->assertArrayHasKey( 'semester_rate', $roomType );
			$this->assertArrayHasKey( 'created_at', $roomType );
			$this->assertArrayHasKey( 'updated_at', $roomType );

			// Check data types (in test environment, price might be integer)
			$this->assertIsInt( $roomType['id'] );
			$this->assertIsString( $roomType['name'] );
			$this->assertIsInt( $roomType['capacity'] );
			$this->assertTrue( is_string( $roomType['daily_rate'] ) || is_numeric( $roomType['daily_rate'] ), 'Daily rate should be string or numeric' );
			$this->assertTrue( is_string( $roomType['semester_rate'] ) || is_numeric( $roomType['semester_rate'] ), 'Semester rate should be string or numeric' );

			// Check capacity is valid
			$this->assertGreaterThan( 0, $roomType['capacity'] );

			// Check price is valid
			$this->assertGreaterThanOrEqual( 0, (float) $roomType['daily_rate'] );
			$this->assertGreaterThanOrEqual( 0, (float) $roomType['semester_rate'] );
		}
	}

	private function loginAsSudo() {
		$response = $this->postJson( '/api/login', [ 
			'email'    => 'sudo@email.com',
			'password' => 'supersecret',
		] );
		return $response->json( 'token' );
	}
}