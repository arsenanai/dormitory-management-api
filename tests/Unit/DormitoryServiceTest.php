<?php

namespace Tests\Unit;

use App\Models\Dormitory;
use App\Models\User;
use App\Models\Role;
use App\Models\Room;
use App\Models\Bed;
use App\Models\RoomType;
use App\Services\DormitoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DormitoryServiceTest extends TestCase {
	use RefreshDatabase;

	private DormitoryService $dormitoryService;
	private User $adminUser;
	private Dormitory $dormitory;

	protected function setUp(): void {
		parent::setUp();

		$this->dormitoryService = new DormitoryService();

		// Create admin role and user
		$adminRole = Role::create( [ 'name' => 'admin' ] );
		$this->adminUser = User::create( [ 
			'name'          => 'Test Admin',
			'first_name'    => 'Test',
			'last_name'     => 'Admin',
			'email'         => 'admin@test.com',
			'password'      => bcrypt( 'password' ),
			'role_id'       => $adminRole->id,
			'status'        => 'approved',
			'phone_numbers' => json_encode( [ '+1234567890' ] )
		] );

		// Create test dormitory
		$this->dormitory = Dormitory::create( [ 
			'name'        => 'Test Dormitory',
			'capacity'    => 200,
			'gender'      => 'mixed',
			'admin_id'    => $this->adminUser->id,
			'address'     => '123 Test Street',
			'description' => 'A test dormitory',
			'phone'       => '+1234567890'
		] );
	}

	public function test_create_dormitory() {
		$dormitoryData = [ 
			'name'        => 'New Dormitory',
			'capacity'    => 300,
			'gender'      => 'female',
			'admin_id'    => $this->adminUser->id,
			'address'     => '456 New Street',
			'description' => 'A new dormitory',
			'phone'       => '+1234567891'
		];

		$result = $this->dormitoryService->createDormitory( $dormitoryData );

		$this->assertInstanceOf( Dormitory::class, $result );
		$this->assertEquals( 'New Dormitory', $result->name );
		$this->assertEquals( 300, $result->capacity );
		$this->assertEquals( 'female', $result->gender );
		$this->assertEquals( $this->adminUser->id, $result->admin_id );
		$this->assertEquals( '456 New Street', $result->address );
		$this->assertEquals( 'A new dormitory', $result->description );
		$this->assertEquals( '+1234567891', $result->phone );

		// Verify it was saved to database
		$this->assertDatabaseHas( 'dormitories', $dormitoryData );
	}

	public function test_get_dormitory_by_id() {
		$result = $this->dormitoryService->getById( $this->dormitory->id );

		$this->assertInstanceOf( Dormitory::class, $result );
		$this->assertEquals( $this->dormitory->id, $result->id );
		$this->assertEquals( 'Test Dormitory', $result->name );
		$this->assertEquals( 200, $result->capacity );
		$this->assertEquals( 'mixed', $result->gender );
		$this->assertEquals( $this->adminUser->id, $result->admin_id );

		// Verify relationships are loaded
		$this->assertInstanceOf( User::class, $result->admin );
		$this->assertEquals( $this->adminUser->id, $result->admin->id );
	}

	public function test_get_dormitory_by_id_with_rooms_and_beds() {
		// Get a room type for creating rooms
		$roomType = RoomType::first();
		if ( ! $roomType ) {
			// Create a default room type if none exists
			$roomType = RoomType::create( [ 
				'name'     => 'standard',
				'beds'     => [ [ 'id' => 1, 'x' => 50, 'y' => 50, 'occupied' => false ] ],
				'capacity' => 2,
				'price'    => 0.00
			] );
		}

		// Create rooms and beds for the dormitory
		$room1 = Room::create( [ 
			'number'       => '101',
			'dormitory_id' => $this->dormitory->id,
			'floor'        => 1,
			'notes'        => 'First floor room',
			'room_type_id' => $roomType->id
		] );

		$room2 = Room::create( [ 
			'number'       => '102',
			'dormitory_id' => $this->dormitory->id,
			'floor'        => 1,
			'notes'        => 'Another first floor room',
			'room_type_id' => $roomType->id
		] );

		// Create beds for room 1
		Bed::create( [ 
			'room_id'     => $room1->id,
			'bed_number'  => '1',
			'is_occupied' => true,
			'user_id'     => null
		] );

		Bed::create( [ 
			'room_id'     => $room1->id,
			'bed_number'  => '2',
			'is_occupied' => false,
			'user_id'     => null
		] );

		// Create beds for room 2
		Bed::create( [ 
			'room_id'     => $room2->id,
			'bed_number'  => '1',
			'is_occupied' => true,
			'user_id'     => null
		] );

		$result = $this->dormitoryService->getById( $this->dormitory->id );

		$this->assertInstanceOf( Dormitory::class, $result );
		$this->assertEquals( 2, $result->rooms->count() );
		$this->assertEquals( 3, $result->rooms->flatMap->beds->count() );

		// Verify rooms are loaded
		$this->assertTrue( $result->rooms->contains( $room1 ) );
		$this->assertTrue( $result->rooms->contains( $room2 ) );
	}

	public function test_update_dormitory() {
		$updateData = [ 
			'name'        => 'Updated Dormitory',
			'capacity'    => 250,
			'gender'      => 'male',
			'address'     => '789 Updated Street',
			'description' => 'An updated dormitory',
			'phone'       => '+1234567892'
		];

		$result = $this->dormitoryService->updateDormitory( $this->dormitory->id, $updateData );

		$this->assertInstanceOf( Dormitory::class, $result );
		$this->assertEquals( 'Updated Dormitory', $result->name );
		$this->assertEquals( 250, $result->capacity );
		$this->assertEquals( 'male', $result->gender );
		$this->assertEquals( '789 Updated Street', $result->address );
		$this->assertEquals( 'An updated dormitory', $result->description );
		$this->assertEquals( '+1234567892', $result->phone );

		// Verify database was updated
		$this->assertDatabaseHas( 'dormitories', array_merge(
			[ 'id' => $this->dormitory->id ],
			$updateData
		) );
	}

	public function test_update_dormitory_partial_data() {
		// Update only some fields
		$updateData = [ 
			'name'     => 'Partially Updated',
			'capacity' => 225
		];

		$result = $this->dormitoryService->updateDormitory( $this->dormitory->id, $updateData );

		$this->assertInstanceOf( Dormitory::class, $result );
		$this->assertEquals( 'Partially Updated', $result->name );
		$this->assertEquals( 225, $result->capacity );

		// Verify other fields remain unchanged
		$this->assertEquals( 'mixed', $result->gender );
		$this->assertEquals( $this->adminUser->id, $result->admin_id );
		$this->assertEquals( '123 Test Street', $result->address );

		// Verify database was updated for changed fields only
		$this->assertDatabaseHas( 'dormitories', [ 
			'id'       => $this->dormitory->id,
			'name'     => 'Partially Updated',
			'capacity' => 225
		] );

		$this->assertDatabaseHas( 'dormitories', [ 
			'id'       => $this->dormitory->id,
			'gender'   => 'mixed', // Unchanged
			'admin_id' => $this->adminUser->id // Unchanged
		] );
	}

	public function test_update_dormitory_preserves_relationships() {
		$updateData = [ 
			'name' => 'Updated with Relationships'
		];

		$result = $this->dormitoryService->updateDormitory( $this->dormitory->id, $updateData );

		$this->assertInstanceOf( Dormitory::class, $result );
		$this->assertEquals( 'Updated with Relationships', $result->name );

		// Verify admin relationship is preserved
		$this->assertEquals( $this->adminUser->id, $result->admin_id );
		$this->assertInstanceOf( User::class, $result->admin );
		$this->assertEquals( $this->adminUser->id, $result->admin->id );
	}

	public function test_list_dormitories_with_computed_fields() {
		// Get a room type for creating rooms
		$roomType = RoomType::first();
		if ( ! $roomType ) {
			// Create a default room type if none exists
			$roomType = RoomType::create( [ 
				'name'     => 'standard',
				'beds'     => [ [ 'id' => 1, 'x' => 50, 'y' => 50, 'occupied' => false ] ],
				'capacity' => 2,
				'price'    => 0.00
			] );
		}

		// Create rooms and beds for the dormitory
		$room1 = Room::create( [ 
			'number'       => '101',
			'dormitory_id' => $this->dormitory->id,
			'floor'        => 1,
			'room_type_id' => $roomType->id
		] );

		$room2 = Room::create( [ 
			'number'       => '102',
			'dormitory_id' => $this->dormitory->id,
			'floor'        => 1,
			'room_type_id' => $roomType->id
		] );

		// Create occupied and free beds
		Bed::create( [ 
			'room_id'     => $room1->id,
			'bed_number'  => '1',
			'is_occupied' => true,
			'user_id'     => null
		] );

		Bed::create( [ 
			'room_id'     => $room1->id,
			'bed_number'  => '2',
			'is_occupied' => false,
			'user_id'     => null
		] );

		Bed::create( [ 
			'room_id'     => $room2->id,
			'bed_number'  => '1',
			'is_occupied' => true,
			'user_id'     => null
		] );

		$result = $this->dormitoryService->listDormitories();

		$this->assertInstanceOf( \Illuminate\Database\Eloquent\Collection::class, $result );

		$dormitory = $result->first();
		$this->assertEquals( $this->dormitory->id, $dormitory->id );
		$this->assertEquals( 'Test Dormitory', $dormitory->name );

		// Verify computed fields
		$this->assertEquals( 2, $dormitory->registered ); // 2 occupied beds
		$this->assertEquals( 1, $dormitory->freeBeds ); // 1 free bed
		$this->assertEquals( 2, $dormitory->rooms_count ); // 2 rooms
	}

	public function test_delete_dormitory() {
		$dormitoryId = $this->dormitory->id;

		$result = $this->dormitoryService->deleteDormitory( $dormitoryId );

		$this->assertEquals( 200, $result->getStatusCode() );

		$data = $result->getData();
		$this->assertEquals( 'Dormitory deleted successfully', $data->message );

		// Verify dormitory was deleted from database
		$this->assertDatabaseMissing( 'dormitories', [ 
			'id' => $dormitoryId
		] );
	}

	public function test_assign_admin_to_dormitory() {
		// Create another admin user
		$newAdminRole = Role::where( 'name', 'admin' )->first();
		$newAdmin = User::create( [ 
			'name'          => 'New Admin',
			'first_name'    => 'New',
			'last_name'     => 'Admin',
			'email'         => 'newadmin@test.com',
			'password'      => bcrypt( 'password' ),
			'role_id'       => $newAdminRole->id,
			'status'        => 'approved',
			'phone_numbers' => json_encode( [ '+1234567891' ] )
		] );

		$this->dormitoryService->assignAdmin( $this->dormitory, $newAdmin );

		$this->assertEquals( $newAdmin->adminDormitory->id, $this->dormitory->id );

		// Verify database was updated
		$this->assertDatabaseHas( 'dormitories', [ 
			'id'       => $this->dormitory->id,
			'admin_id' => $newAdmin->id
		] );

		// Verify old admin is no longer assigned
		$this->assertDatabaseMissing( 'dormitories', [ 
			'id'       => $this->dormitory->id,
			'admin_id' => $this->adminUser->id
		] );
	}

	public function test_get_rooms_for_dormitory() {
		// Get a room type for creating rooms
		$roomType = RoomType::first();
		if ( ! $roomType ) {
			// Create a default room type if none exists
			$roomType = RoomType::create( [ 
				'name'     => 'standard',
				'beds'     => [ [ 'id' => 1, 'x' => 50, 'y' => 50, 'occupied' => false ] ],
				'capacity' => 2,
				'price'    => 0.00
			] );
		}

		// Create rooms for the dormitory
		$room1 = Room::create( [ 
			'number'       => '101',
			'dormitory_id' => $this->dormitory->id,
			'floor'        => 1,
			'notes'        => 'First floor room',
			'room_type_id' => $roomType->id
		] );

		$room2 = Room::create( [ 
			'number'       => '102',
			'dormitory_id' => $this->dormitory->id,
			'floor'        => 1,
			'notes'        => 'Another first floor room',
			'room_type_id' => $roomType->id
		] );

		$result = $this->dormitoryService->getRoomsForDormitory( $this->dormitory->id );

		$this->assertIsObject( $result );
		$this->assertEquals( 2, $result->count() );

		// Verify rooms are returned
		$this->assertTrue( $result->contains( $room1 ) );
		$this->assertTrue( $result->contains( $room2 ) );

		// Verify room details
		$room = $result->first();
		$this->assertEquals( '101', $room->number );
		$this->assertEquals( 1, $room->floor );
		$this->assertEquals( 'First floor room', $room->notes );
	}

	public function test_dormitory_service_handles_empty_dormitory() {
		// Test with dormitory that has no rooms
		$result = $this->dormitoryService->listDormitories();

		$this->assertInstanceOf( \Illuminate\Database\Eloquent\Collection::class, $result );

		$dormitory = $result->first();
		$this->assertEquals( $this->dormitory->id, $dormitory->id );

		// Verify computed fields for empty dormitory
		$this->assertEquals( 0, $dormitory->registered );
		$this->assertEquals( 0, $dormitory->freeBeds );
		$this->assertEquals( 0, $dormitory->rooms_count );
	}

	public function test_dormitory_service_handles_large_numbers() {
		// Test with large capacity values
		$largeDormitory = Dormitory::create( [ 
			'name'        => 'Large Dormitory',
			'capacity'    => 10000,
			'gender'      => 'mixed',
			'admin_id'    => $this->adminUser->id,
			'address'     => 'Large Address',
			'description' => 'A very large dormitory',
			'phone'       => '+1234567899'
		] );

		$result = $this->dormitoryService->getById( $largeDormitory->id );

		$this->assertInstanceOf( Dormitory::class, $result );
		$this->assertEquals( 10000, $result->capacity );
	}

	public function test_dormitory_service_handles_special_characters() {
		// Test with special characters in text fields
		$specialData = [ 
			'name'        => 'Dormitory with Special Chars: !@#$%^&*()',
			'address'     => 'Address with "quotes" and \'apostrophes\'',
			'description' => 'Description with <script>alert("xss")</script> tags',
			'phone'       => '+1 (234) 567-8900'
		];

		$result = $this->dormitoryService->createDormitory( array_merge( $specialData, [ 
			'capacity' => 100,
			'gender'   => 'mixed',
			'admin_id' => $this->adminUser->id
		] ) );

		$this->assertInstanceOf( Dormitory::class, $result );
		$this->assertEquals( $specialData['name'], $result->name );
		$this->assertEquals( $specialData['address'], $result->address );
		$this->assertEquals( $specialData['description'], $result->description );
		$this->assertEquals( $specialData['phone'], $result->phone );

		// Verify database contains special characters
		$this->assertDatabaseHas( 'dormitories', $specialData );
	}

	public function test_dormitory_service_handles_unicode_characters() {
		// Test with unicode characters
		$unicodeData = [ 
			'name'        => 'Dormitory with Unicode: 中文, русский, العربية',
			'address'     => 'Address with emojis: 🏠 🏢 🏫',
			'description' => 'Description with accented characters: café, naïve, résumé'
		];

		$result = $this->dormitoryService->createDormitory( array_merge( $unicodeData, [ 
			'capacity' => 100,
			'gender'   => 'mixed',
			'admin_id' => $this->adminUser->id,
			'phone'    => '+1234567890'
		] ) );

		$this->assertInstanceOf( Dormitory::class, $result );
		$this->assertEquals( $unicodeData['name'], $result->name );
		$this->assertEquals( $unicodeData['address'], $result->address );
		$this->assertEquals( $unicodeData['description'], $result->description );

		// Verify database contains unicode characters
		$this->assertDatabaseHas( 'dormitories', $unicodeData );
	}
}
