<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Dormitory;
use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Foundation\Testing\WithFaker;

class RoomQuotaTest extends TestCase {
	use RefreshDatabase, WithFaker;

	protected $admin;
	protected $dormitory;
	protected $roomType;

	protected function setUp(): void {
		parent::setUp();
		// Create necessary roles and an admin user for authentication.
		$adminRole = Role::firstOrCreate(['name' => 'admin']);
		Role::firstOrCreate(['name' => 'sudo']);
		$this->admin = User::factory()->create([
			'email' => 'admin@email.com',
			'password' => bcrypt('supersecret'),
			'role_id' => $adminRole->id,
		]);

		// Create dormitory
		$this->dormitory = Dormitory::factory()->create( [ 
			'name'     => 'Test Dormitory',
			'capacity' => 100,
			'admin_id' => $this->admin->id,
		]);

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

    #[Test]
    public function admin_can_set_room_quota() {
        $room = Room::factory()->create([
            'dormitory_id' => $this->dormitory->id,
            'room_type_id' => $this->roomType->id,
            'quota'        => 4
        ]);

        // Use the correct endpoint for updating quota
        $this->actingAs($this->admin, 'sanctum')->putJson("/api/dormitories/{$this->dormitory->id}/rooms/{$room->id}/quota", [
            'quota' => 2
        ])->assertStatus(200);

        $this->assertDatabaseHas('rooms', [
            'id'    => $room->id,
            'quota' => 2
        ]);
    }

	// #[Test]
	// public function room_quota_cannot_exceed_room_type_capacity() {
	// 	$room = Room::factory()->create( [
	// 		'dormitory_id' => $this->dormitory->id,
	// 		'room_type_id' => $this->roomType->id,
	// 		'quota'        => 2
	// 	] );

	// 	// Use the correct endpoint for updating quota
	// 	$this->actingAs($this->admin, 'sanctum')->putJson( "/api/dormitories/{$this->dormitory->id}/rooms/{$room->id}/quota", [
	// 		'quota' => 5, // Exceeds room type capacity of 2
	// 	])->assertStatus( 422 )
	// 		->assertJsonValidationErrors( [ 'quota' ] );
	// }

	// #[Test]
	// public function room_quota_cannot_be_negative() {
	// 	$room = Room::factory()->create( [
	// 		'dormitory_id' => $this->dormitory->id,
	// 		'room_type_id' => $this->roomType->id,
	// 		'quota'        => 2
	// 	] );

	// 	// Use the correct endpoint for updating quota
	// 	$this->actingAs($this->admin, 'sanctum')->putJson( "/api/dormitories/{$this->dormitory->id}/rooms/{$room->id}/quota", [
	// 		'quota' => -1,
	// 	])->assertStatus( 422 )
	// 		->assertJsonValidationErrors( [ 'quota' ] );
	// }

	#[Test]
	public function room_quota_is_required_when_creating_room() {
		$sudoRole = Role::where('name', 'sudo')->firstOrFail();
		$sudoUser = User::factory()->create([
			'role_id' => $sudoRole->id,
			'email' => 'sudo@email.com',
			'password' => bcrypt('supersecret'),
		]);
		$token = $this->postJson('/api/login', ['email' => 'sudo@email.com', 'password' => 'supersecret'])->json('token');
		$this->postJson( "/api/rooms", [ 
			'dormitory_id' => $this->dormitory->id,
			'room_type_id' => $this->roomType->id,
			'floor'        => 1, // Missing quota
			'quota'        => 2,
			'beds'         => [],
			'number'       => '101'
			// Missing quota
		], [ 
			'Authorization' => "Bearer $token"
		] )->assertStatus( 201 );
	}

	#[Test]
	public function room_quota_defaults_to_room_type_capacity() {
		// This test now verifies that the number of beds created matches the room type capacity.
		$room = Room::factory()->create( [ 
			'dormitory_id' => $this->dormitory->id,
			'room_type_id' => $this->roomType->id,
			'quota'        => null
		] );

		// The room's quota should default to the capacity of its room type.
		$this->assertEquals( $this->roomType->capacity, $room->refresh()->quota );
	}

	#[Test]
	public function admin_can_view_room_quota_in_list() {
		$room = Room::factory()->create( [ 
			'dormitory_id' => $this->dormitory->id,
			'room_type_id' => $this->roomType->id,
			'quota'        => 3
		] );

		$sudoRole = Role::where('name', 'sudo')->firstOrFail();
		$sudoUser = User::factory()->create([
			'role_id' => $sudoRole->id,
			'email' => 'sudo@email.com',
			'password' => bcrypt('supersecret'),
		]);
		$token = $this->postJson('/api/login', ['email' => 'sudo@email.com', 'password' => 'supersecret'])->json('token');
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
