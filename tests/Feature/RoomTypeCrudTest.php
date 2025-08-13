<?php

namespace Tests\Feature;

use App\Models\RoomType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RoomTypeCrudTest extends TestCase {
	use RefreshDatabase;

	protected function setUp(): void {
		parent::setUp();
		$this->seed();
	}

	public function test_can_create_standard_room_type() {
		Storage::fake( 'public' );
		$token = $this->loginAsSudo();

		$payload = [ 
			'name'     => 'standard',
			'capacity' => 2,
			'price'    => 150.00,
			'minimap'  => UploadedFile::fake()->image( 'minimap.jpg' ),
			'beds'     => [ 
				[ 'x' => 10, 'y' => 20, 'width' => 30, 'height' => 40 ],
				[ 'x' => 50, 'y' => 60, 'width' => 30, 'height' => 40 ],
			],
			'photos'   => [ 
				UploadedFile::fake()->image( 'photo1.jpg' ),
				UploadedFile::fake()->image( 'photo2.jpg' ),
			],
		];

		$response = $this->postJson( '/api/room-types', [ 
			'name'     => $payload['name'],
			'capacity' => $payload['capacity'],
			'price'    => $payload['price'],
			'minimap'  => $payload['minimap'],
			'beds'     => json_encode( $payload['beds'] ),
			'photos'   => $payload['photos'],
		], [ 
			'Authorization' => "Bearer $token",
		] );

		$response->assertStatus( 201 );
		$this->assertDatabaseHas( 'room_types', [ 
			'name'     => 'standard',
			'capacity' => 2,
			'price'    => 150.00,
		] );
		Storage::disk( 'public' )->assertExists( 'minimaps/' . $payload['minimap']->hashName() );
		Storage::disk( 'public' )->assertExists( 'photos/' . $payload['photos'][0]->hashName() );
		Storage::disk( 'public' )->assertExists( 'photos/' . $payload['photos'][1]->hashName() );
	}

	public function test_can_create_lux_room_type() {
		Storage::fake( 'public' );
		$token = $this->loginAsSudo();

		$payload = [ 
			'name'     => 'lux',
			'capacity' => 1,
			'price'    => 300.00,
			'minimap'  => UploadedFile::fake()->image( 'minimap.jpg' ),
			'beds'     => [ 
				[ 'x' => 10, 'y' => 20, 'width' => 30, 'height' => 40 ],
			],
			'photos'   => [ 
				UploadedFile::fake()->image( 'photo1.jpg' ),
			],
		];

		$response = $this->postJson( '/api/room-types', [ 
			'name'     => $payload['name'],
			'capacity' => $payload['capacity'],
			'price'    => $payload['price'],
			'minimap'  => $payload['minimap'],
			'beds'     => json_encode( $payload['beds'] ),
			'photos'   => $payload['photos'],
		], [ 
			'Authorization' => "Bearer $token",
		] );

		$response->assertStatus( 201 );
		$this->assertDatabaseHas( 'room_types', [ 
			'name'     => 'lux',
			'capacity' => 1,
			'price'    => 300.00,
		] );
	}

	public function test_cannot_create_room_type_with_invalid_name() {
		$token = $this->loginAsSudo();

		$response = $this->postJson( '/api/room-types', [ 
			'name'     => 'invalid',
			'capacity' => 2,
			'price'    => 150.00,
		], [ 
			'Authorization' => "Bearer $token",
		] );

		$response->assertStatus( 422 );
		$response->assertJsonValidationErrors( [ 'name' ] );
	}

	public function test_cannot_create_room_type_without_required_fields() {
		$token = $this->loginAsSudo();

		$response = $this->postJson( '/api/room-types', [ 
			'minimap' => UploadedFile::fake()->image( 'minimap.jpg' ),
		], [ 
			'Authorization' => "Bearer $token",
		] );

		$response->assertStatus( 422 );
		$response->assertJsonValidationErrors( [ 'name', 'capacity', 'price' ] );
	}

	public function test_can_update_room_type() {
		$token = $this->loginAsSudo();
		$roomType = RoomType::factory()->create( [ 
			'name'     => 'standard',
			'capacity' => 2,
			'price'    => 150.00,
		] );

		$response = $this->putJson( "/api/room-types/{$roomType->id}", [ 
			'name'     => 'lux',
			'capacity' => 1,
			'price'    => 300.00,
			'beds'     => json_encode( [ 
				[ 'x' => 1, 'y' => 2, 'width' => 3, 'height' => 4 ],
			] ),
		], [ 
			'Authorization' => "Bearer $token",
		] );

		$response->assertStatus( 200 );
		$this->assertDatabaseHas( 'room_types', [ 
			'id'       => $roomType->id,
			'name'     => 'lux',
			'capacity' => 1,
			'price'    => 300.00,
		] );
	}

	public function test_can_update_room_type_photos() {
		Storage::fake( 'public' );
		$token = $this->loginAsSudo();
		$roomType = RoomType::factory()->create( [ 
			'name'     => 'standard',
			'capacity' => 2,
			'price'    => 150.00,
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
			'name'     => 'standard',
			'capacity' => 2,
			'price'    => 150.00,
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

		// Create some room types
		RoomType::factory()->create( [ 
			'name'     => 'standard',
			'capacity' => 2,
			'price'    => 150.00,
		] );
		RoomType::factory()->create( [ 
			'name'     => 'lux',
			'capacity' => 1,
			'price'    => 300.00,
		] );

		$response = $this->getJson( '/api/room-types', [ 
			'Authorization' => "Bearer $token",
		] );

		$response->assertStatus( 200 );
		$response->assertJsonFragment( [ 
			'name'     => 'standard',
			'capacity' => 2,
			'price'    => 150.00,
		] );
		$response->assertJsonFragment( [ 
			'name'     => 'lux',
			'capacity' => 1,
			'price'    => 300.00,
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
		$this->assertEquals( '150.00', $standard['price'] );

		// Verify lux room type
		$this->assertNotNull( $lux );
		$this->assertEquals( 1, $lux['capacity'] );
		$this->assertEquals( '300.00', $lux['price'] );
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
			$this->assertArrayHasKey( 'price', $roomType );
			$this->assertArrayHasKey( 'created_at', $roomType );
			$this->assertArrayHasKey( 'updated_at', $roomType );

			// Check data types (in test environment, price might be integer)
			$this->assertIsInt( $roomType['id'] );
			$this->assertIsString( $roomType['name'] );
			$this->assertIsInt( $roomType['capacity'] );
			$this->assertTrue( is_string( $roomType['price'] ) || is_numeric( $roomType['price'] ), 'Price should be string or numeric' );

			// Check name is valid
			$this->assertContains( $roomType['name'], [ 'standard', 'lux' ] );

			// Check capacity is valid
			$this->assertGreaterThan( 0, $roomType['capacity'] );
			$this->assertLessThanOrEqual( 4, $roomType['capacity'] );

			// Check price is valid
			$this->assertGreaterThanOrEqual( 0, (float) $roomType['price'] );
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