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

	public function test_can_create_room_type_with_minimap_and_beds() {
		Storage::fake( 'public' );
		$token = $this->loginAsSudo();

		$payload = [ 
			'name'    => 'standard',
			'minimap' => UploadedFile::fake()->image( 'minimap.jpg' ),
			'beds'    => [ 
				[ 'x' => 10, 'y' => 20, 'width' => 30, 'height' => 40 ],
				[ 'x' => 50, 'y' => 60, 'width' => 30, 'height' => 40 ],
			],
			'photos'  => [ 
				UploadedFile::fake()->image( 'photo1.jpg' ),
				UploadedFile::fake()->image( 'photo2.jpg' ),
			],
		];

		$response = $this->postJson( '/api/room-types', [ 
			'name'    => $payload['name'],
			'minimap' => $payload['minimap'],
			'beds'    => json_encode( $payload['beds'] ),
			'photos'  => $payload['photos'],
		], [ 
			'Authorization' => "Bearer $token",
		] );

		$response->assertStatus( 201 );
		$this->assertDatabaseHas( 'room_types', [ 
			'name' => 'standard',
		] );
		Storage::disk( 'public' )->assertExists( 'minimaps/' . $payload['minimap']->hashName() );
		Storage::disk( 'public' )->assertExists( 'photos/' . $payload['photos'][0]->hashName() );
		Storage::disk( 'public' )->assertExists( 'photos/' . $payload['photos'][1]->hashName() );
	}

	public function test_can_update_room_type() {
		$token = $this->loginAsSudo();
		$roomType = RoomType::factory()->create();

		$response = $this->putJson( "/api/room-types/{$roomType->id}", [ 
			'name' => 'lux',
			'beds' => json_encode( [ 
				[ 'x' => 1, 'y' => 2, 'width' => 3, 'height' => 4 ],
			] ),
		], [ 
			'Authorization' => "Bearer $token",
		] );

		$response->assertStatus( 200 );
		$this->assertDatabaseHas( 'room_types', [ 
			'id'   => $roomType->id,
			'name' => 'lux',
		] );
	}

	public function test_can_update_room_type_photos() {
		Storage::fake( 'public' );
		$token = $this->loginAsSudo();
		$roomType = RoomType::factory()->create();

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
		$roomType = RoomType::factory()->create();

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
		RoomType::factory()->create( [ 'name' => 'standard' ] );
		RoomType::factory()->create( [ 'name' => 'lux' ] );

		$response = $this->getJson( '/api/room-types', [ 
			'Authorization' => "Bearer $token",
		] );

		$response->assertStatus( 200 );
		$response->assertJsonFragment( [ 'name' => 'standard' ] );
		$response->assertJsonFragment( [ 'name' => 'lux' ] );
	}

	private function loginAsSudo() {
		$response = $this->postJson( '/api/login', [ 
			'email'    => 'sudo@email.com',
			'password' => 'supersecret',
		] );
		return $response->json( 'token' );
	}
}