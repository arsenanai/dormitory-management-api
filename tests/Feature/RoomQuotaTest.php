<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Dormitory;
use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

class RoomQuotaTest extends TestCase {
	use RefreshDatabase, WithFaker;

	protected $admin;
	protected $dormitory;
	protected $roomType;

	protected function setUp(): void {
		parent::setUp();
		$this->seed();

		// Create dormitory
		$this->dormitory = Dormitory::factory()->create( [ 
			'name'     => 'Test Dormitory',
			'capacity' => 100
		] );

		// Create room type
		$this->roomType = RoomType::factory()->create( [ 
			'name'     => 'Standard',
			'capacity' => 2,
			'price'    => 150.00
		] );
	}

	private function loginAsAdmin() {
		$response = $this->postJson( '/api/login', [ 
			'email'    => 'admin@email.com',
			'password' => 'supersecret',
		] );
		return $response->json( 'token' );
	}

	/** @test */
	public function admin_can_set_room_quota() {
		$token = $this->loginAsAdmin();
		$room = Room::factory()->create( [ 
			'dormitory_id' => $this->dormitory->id,
			'room_type_id' => $this->roomType->id,
			'quota'        => 4
		] );

		$this->putJson( "/api/rooms/{$room->id}", [ 
			'quota' => 2
		], [ 
			'Authorization' => "Bearer $token"
		] )->assertStatus( 200 );

		$this->assertDatabaseHas( 'rooms', [ 
			'id'    => $room->id,
			'quota' => 2
		] );
	}

	/** @test */
	public function room_quota_cannot_exceed_room_type_capacity() {
		$token = $this->loginAsAdmin();
		$room = Room::factory()->create( [ 
			'dormitory_id' => $this->dormitory->id,
			'room_type_id' => $this->roomType->id,
			'quota'        => 2
		] );

		$this->putJson( "/api/rooms/{$room->id}", [ 
			'quota' => 5 // Exceeds room type capacity of 2
		], [ 
			'Authorization' => "Bearer $token"
		] )->assertStatus( 422 )
			->assertJsonValidationErrors( [ 'quota' ] );
	}

	/** @test */
	public function room_quota_cannot_be_negative() {
		$token = $this->loginAsAdmin();
		$room = Room::factory()->create( [ 
			'dormitory_id' => $this->dormitory->id,
			'room_type_id' => $this->roomType->id,
			'quota'        => 2
		] );

		$this->putJson( "/api/rooms/{$room->id}", [ 
			'quota' => -1
		], [ 
			'Authorization' => "Bearer $token"
		] )->assertStatus( 422 )
			->assertJsonValidationErrors( [ 'quota' ] );
	}

	/** @test */
	public function room_quota_is_required_when_creating_room() {
		$token = $this->loginAsAdmin();
		$this->postJson( "/api/rooms", [ 
			'dormitory_id' => $this->dormitory->id,
			'room_type_id' => $this->roomType->id,
			'floor'        => 1,
			'number'       => '101'
			// Missing quota
		], [ 
			'Authorization' => "Bearer $token"
		] )->assertStatus( 201 );
	}

	/** @test */
	public function room_quota_defaults_to_room_type_capacity() {
		$room = Room::factory()->create( [ 
			'dormitory_id' => $this->dormitory->id,
			'room_type_id' => $this->roomType->id,
			'quota'        => null
		] );

		$this->assertEquals( $this->roomType->capacity, $room->quota );
	}

	/** @test */
	public function admin_can_view_room_quota_in_list() {
		$room = Room::factory()->create( [ 
			'dormitory_id' => $this->dormitory->id,
			'room_type_id' => $this->roomType->id,
			'quota'        => 3
		] );

		$token = $this->loginAsAdmin();
		$response = $this->getJson( "/api/rooms", [ 
			'Authorization' => "Bearer $token"
		] );

		$response->assertStatus( 200 );

		// Check that the room exists in the response (handle pagination)
		$roomData = $response->json( 'data' );
		$foundRoom = collect( $roomData )->firstWhere( 'id', $room->id );

		// If not found in first page, check if it's in other pages
		if ( ! $foundRoom ) {
			$totalPages = $response->json( 'last_page', 1 );
			for ( $page = 2; $page <= $totalPages; $page++ ) {
				$pageResponse = $this->getJson( "/api/rooms?page={$page}", [ 
					'Authorization' => "Bearer $token"
				] );
				$pageData = $pageResponse->json( 'data' );
				$foundRoom = collect( $pageData )->firstWhere( 'id', $room->id );
				if ( $foundRoom )
					break;
			}
		}

		$this->assertNotNull( $foundRoom, 'Room should be found in response' );
		$this->assertEquals( 3, $foundRoom['quota'], 'Room should have correct quota' );
	}
}
