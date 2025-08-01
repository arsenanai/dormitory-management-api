<?php

namespace Tests\Feature;

use App\Models\Room;
use App\Models\Dormitory;
use App\Models\RoomType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoomCrudTest extends TestCase {
	use RefreshDatabase;

	protected function setUp(): void {
		parent::setUp();
		$this->seed();
	}

	public function test_can_create_room() {
		$token = $this->loginAsSudo();
		$dormitory = Dormitory::factory()->create();
		$roomType = RoomType::factory()->create();

		$payload = [ 
			'number'       => 'A101',
			'floor'        => 1,
			'notes'        => 'Corner room',
			'dormitory_id' => $dormitory->id,
			'room_type_id' => $roomType->id,
		];

		$response = $this->postJson( '/api/rooms', $payload, [ 
			'Authorization' => "Bearer $token",
		] );

		$response->assertStatus( 201 );
		$this->assertDatabaseHas( 'rooms', [ 
			'number'       => 'A101',
			'dormitory_id' => $dormitory->id,
			'room_type_id' => $roomType->id,
		] );
	}

	public function test_can_update_room() {
		$token = $this->loginAsSudo();
		$room = Room::factory()->create();

		$response = $this->putJson( "/api/rooms/{$room->id}", [ 
			'notes' => 'Updated notes',
		], [ 
			'Authorization' => "Bearer $token",
		] );

		$response->assertStatus( 200 );
		$this->assertDatabaseHas( 'rooms', [ 
			'id'    => $room->id,
			'notes' => 'Updated notes',
		] );
	}

	public function test_can_delete_room() {
		$token = $this->loginAsSudo();
		$room = Room::factory()->create();

		$response = $this->deleteJson( "/api/rooms/{$room->id}", [], [ 
			'Authorization' => "Bearer $token",
		] );

		$response->assertStatus( 200 );
		$this->assertDatabaseMissing( 'rooms', [ 
			'id' => $room->id,
		] );
	}

	public function test_can_list_rooms() {
		$token = $this->loginAsSudo();

		// Clear existing rooms to ensure clean test environment
		Room::query()->delete();

		Room::factory()->create( [ 'number' => 'A101' ] );
		Room::factory()->create( [ 'number' => 'B202' ] );

		$response = $this->getJson( '/api/rooms', [ 
			'Authorization' => "Bearer $token",
		] );

		$response->assertStatus( 200 );
		$response->assertJsonFragment( [ 'number' => 'A101' ] );
		$response->assertJsonFragment( [ 'number' => 'B202' ] );
	}

	public function test_can_list_rooms_with_pagination() {
		$token = $this->loginAsSudo();

		// Create 25 rooms
		Room::factory()->count( 25 )->create();

		// Request first page with 10 per page
		$response = $this->getJson( '/api/rooms?per_page=10', [ 
			'Authorization' => "Bearer $token",
		] );

		$response->assertStatus( 200 );
		$response->assertJsonStructure( [ 'data', 'links', 'current_page' ] );
		$this->assertCount( 10, $response->json( 'data' ) );
	}

	public function test_can_filter_rooms_by_dormitory_and_room_type() {
		$token = $this->loginAsSudo();

		$dorm1 = Dormitory::factory()->create();
		$dorm2 = Dormitory::factory()->create();
		$type1 = RoomType::factory()->create();
		$type2 = RoomType::factory()->create();

		$roomA = Room::factory()->create( [ 'dormitory_id' => $dorm1->id, 'room_type_id' => $type1->id, 'number' => 'A101' ] );
		$roomB = Room::factory()->create( [ 'dormitory_id' => $dorm2->id, 'room_type_id' => $type2->id, 'number' => 'B202' ] );

		// Filter by dormitory_id
		$response = $this->getJson( '/api/rooms?dormitory_id=' . $dorm1->id, [ 
			'Authorization' => "Bearer $token",
		] );
		$response->assertStatus( 200 );
		$response->assertJsonFragment( [ 'number' => 'A101' ] );
		$response->assertJsonMissing( [ 'number' => 'B202' ] );

		// Filter by room_type_id
		$response = $this->getJson( '/api/rooms?room_type_id=' . $type2->id, [ 
			'Authorization' => "Bearer $token",
		] );
		$response->assertStatus( 200 );
		$response->assertJsonFragment( [ 'number' => 'B202' ] );
		$response->assertJsonMissing( [ 'number' => 'A101' ] );
	}

	public function test_can_filter_rooms_by_floor_and_number() {
		$token = $this->loginAsSudo();

		Room::factory()->create( [ 'number' => 'A101', 'floor' => 1 ] );
		Room::factory()->create( [ 'number' => 'B202', 'floor' => 2 ] );
		Room::factory()->create( [ 'number' => 'C303', 'floor' => 3 ] );

		// Filter by floor
		$response = $this->getJson( '/api/rooms?floor=2', [ 
			'Authorization' => "Bearer $token",
		] );
		$response->assertStatus( 200 );
		$response->assertJsonFragment( [ 'number' => 'B202' ] );
		$response->assertJsonMissing( [ 'number' => 'A101' ] );
		$response->assertJsonMissing( [ 'number' => 'C303' ] );

		// Filter by partial number
		$response = $this->getJson( '/api/rooms?number=A1', [ 
			'Authorization' => "Bearer $token",
		] );
		$response->assertStatus( 200 );
		$response->assertJsonFragment( [ 'number' => 'A101' ] );
		$response->assertJsonMissing( [ 'number' => 'B202' ] );
		$response->assertJsonMissing( [ 'number' => 'C303' ] );
	}

	private function loginAsSudo() {
		$response = $this->postJson( '/api/login', [ 
			'email'    => 'sudo@email.com',
			'password' => 'supersecret',
		] );
		return $response->json( 'token' );
	}
}