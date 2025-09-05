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
		// Don't run the full seeder to avoid conflicts with test data
		// $this->seed();
	}

	public function test_can_create_room() {
		// Create necessary test data first
		$this->createTestData();
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
		// Create necessary test data first
		$this->createTestData();
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
		// Create necessary test data first
		$this->createTestData();
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
		// Create necessary test data
		$sudoRole = \App\Models\Role::create( [ 'name' => 'sudo' ] );
		$sudoUser = \App\Models\User::create( [ 
			'name'     => 'Sudo User',
			'email'    => 'sudo@email.com',
			'password' => bcrypt( 'supersecret' ),
			'role_id'  => $sudoRole->id,
			'status'   => 'active'
		] );

		$token = $this->loginAsSudo();

		// Create rooms with proper dormitory assignment
		$dormitory = Dormitory::factory()->create();
		$roomType = RoomType::factory()->create();

		$room1 = Room::factory()->create( [ 
			'number'       => 'TEST101',
			'dormitory_id' => $dormitory->id,
			'room_type_id' => $roomType->id
		] );
		$room2 = Room::factory()->create( [ 
			'number'       => 'TEST202',
			'dormitory_id' => $dormitory->id,
			'room_type_id' => $roomType->id
		] );

		$response = $this->getJson( '/api/rooms', [ 
			'Authorization' => "Bearer $token",
		] );

		$response->assertStatus( 200 );
		$responseData = $response->json();
		$this->assertArrayHasKey( 'data', $responseData );
		$this->assertGreaterThan( 0, count( $responseData['data'] ) );

		// Check that our created rooms are in the response
		$roomNumbers = collect( $responseData['data'] )->pluck( 'number' )->toArray();
		$this->assertContains( 'TEST101', $roomNumbers );
		$this->assertContains( 'TEST202', $roomNumbers );

		// Verify the rooms have the expected structure
		$roomTEST101 = collect( $responseData['data'] )->firstWhere( 'number', 'TEST101' );
		$roomTEST202 = collect( $responseData['data'] )->firstWhere( 'number', 'TEST202' );
		$this->assertNotNull( $roomTEST101 );
		$this->assertNotNull( $roomTEST202 );
		$this->assertEquals( $room1->id, $roomTEST101['id'] );
		$this->assertEquals( $room2->id, $roomTEST202['id'] );
	}

	public function test_can_list_rooms_with_pagination() {
		// Create necessary test data
		$sudoRole = \App\Models\Role::create( [ 'name' => 'sudo' ] );
		$sudoUser = \App\Models\User::create( [ 
			'name'     => 'Sudo User',
			'email'    => 'sudo@email.com',
			'password' => bcrypt( 'supersecret' ),
			'role_id'  => $sudoRole->id,
			'status'   => 'active'
		] );

		$token = $this->loginAsSudo();

		// Create 25 rooms
		$dormitory = Dormitory::factory()->create();
		$roomType = RoomType::factory()->create();

		for ( $i = 1; $i <= 25; $i++ ) {
			Room::factory()->create( [ 
				'number'       => 'PAGE' . $i,
				'dormitory_id' => $dormitory->id,
				'room_type_id' => $roomType->id
			] );
		}

		// Request first page with 10 per page
		$response = $this->getJson( '/api/rooms?per_page=10', [ 
			'Authorization' => "Bearer $token",
		] );

		$response->assertStatus( 200 );
		$response->assertJsonStructure( [ 'data', 'links', 'current_page' ] );
		$this->assertCount( 10, $response->json( 'data' ) );
	}

	public function test_can_filter_rooms_by_dormitory_and_room_type() {
		// Create necessary test data first
		$this->createTestData();
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
		$responseData = $response->json();
		$this->assertArrayHasKey( 'data', $responseData );
		$this->assertGreaterThan( 0, count( $responseData['data'] ) );

		// Check that all returned rooms belong to the specified dormitory
		foreach ( $responseData['data'] as $room ) {
			$this->assertEquals( $dorm1->id, $room['dormitory_id'] );
		}

		// Filter by room_type_id
		$response = $this->getJson( '/api/rooms?room_type_id=' . $type2->id, [ 
			'Authorization' => "Bearer $token",
		] );
		$response->assertStatus( 200 );
		$responseData = $response->json();
		$this->assertArrayHasKey( 'data', $responseData );
		$this->assertGreaterThan( 0, count( $responseData['data'] ) );

		// Check that all returned rooms belong to the specified room type
		foreach ( $responseData['data'] as $room ) {
			$this->assertEquals( $type2->id, $room['room_type_id'] );
		}
	}

	public function test_can_filter_rooms_by_floor_and_number() {
		// Create necessary test data first
		$this->createTestData();
		$token = $this->loginAsSudo();

		Room::factory()->create( [ 'number' => 'A101', 'floor' => 1 ] );
		Room::factory()->create( [ 'number' => 'B202', 'floor' => 2 ] );
		Room::factory()->create( [ 'number' => 'C303', 'floor' => 3 ] );

		// Filter by floor
		$response = $this->getJson( '/api/rooms?floor=2', [ 
			'Authorization' => "Bearer $token",
		] );
		$response->assertStatus( 200 );
		$responseData = $response->json();
		$this->assertArrayHasKey( 'data', $responseData );
		$this->assertGreaterThan( 0, count( $responseData['data'] ) );

		// Check that all returned rooms are on floor 2
		foreach ( $responseData['data'] as $room ) {
			$this->assertEquals( 2, $room['floor'] );
		}

		// Filter by partial number
		$response = $this->getJson( '/api/rooms?number=A1', [ 
			'Authorization' => "Bearer $token",
		] );
		$response->assertStatus( 200 );
		$responseData = $response->json();
		$this->assertArrayHasKey( 'data', $responseData );
		$this->assertGreaterThan( 0, count( $responseData['data'] ) );

		// Check that all returned rooms have numbers containing 'A1'
		foreach ( $responseData['data'] as $room ) {
			$this->assertStringContainsString( 'A1', $room['number'] );
		}
	}

	private function createTestData() {
		// Create sudo role if it doesn't exist
		$sudoRole = \App\Models\Role::firstOrCreate(
			[ 'name' => 'sudo' ],
			[ 'name' => 'sudo' ]
		);

		// Create sudo user if it doesn't exist
		\App\Models\User::firstOrCreate(
			[ 'email' => 'sudo@email.com' ],
			[ 
				'name'     => 'Sudo User',
				'email'    => 'sudo@email.com',
				'password' => bcrypt( 'supersecret' ),
				'role_id'  => $sudoRole->id,
				'status'   => 'active'
			]
		);
	}

	private function loginAsSudo() {
		$response = $this->postJson( '/api/login', [ 
			'email'    => 'sudo@email.com',
			'password' => 'supersecret',
		] );
		return $response->json( 'token' );
	}
}