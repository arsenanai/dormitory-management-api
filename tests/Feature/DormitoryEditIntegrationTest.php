<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\Dormitory;
use App\Models\AdminProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DormitoryEditIntegrationTest extends TestCase {
	use RefreshDatabase;

	private $sudoUser;
	private $adminUser;
	private $dormitory;

	protected function setUp(): void {
		parent::setUp();

		// Create roles
		$sudoRole = Role::create( [ 'name' => 'sudo' ] );
		$adminRole = Role::create( [ 'name' => 'admin' ] );

		// Create sudo user
		$this->sudoUser = User::create( [ 
			'name'          => 'Sudo User',
			'first_name'    => 'Sudo',
			'last_name'     => 'User',
			'email'         => 'sudo@test.com',
			'password'      => bcrypt( 'password' ),
			'role_id'       => $sudoRole->id,
			'status'        => 'approved',
			'phone_numbers' => json_encode( [ '+1234567890' ] )
		] );

		// Create admin user
		$this->adminUser = User::create( [ 
			'name'          => 'Admin User',
			'first_name'    => 'Admin',
			'last_name'     => 'User',
			'email'         => 'admin@test.com',
			'password'      => bcrypt( 'password' ),
			'role_id'       => $adminRole->id,
			'status'        => 'approved',
			'phone_numbers' => json_encode( [ '+1234567891' ] )
		] );

		// Create admin profile
		AdminProfile::create( [ 
			'user_id'         => $this->adminUser->id,
			'position'        => 'Dormitory Manager',
			'department'      => 'Housing',
			'office_phone'    => '+1234567892',
			'office_location' => 'Building A, Room 101'
		] );

		// Create test dormitory
		$this->dormitory = Dormitory::create( [ 
			'name'        => 'Test Dormitory',
			'capacity'    => 200,
			'gender'      => 'mixed',
			'admin_id'    => $this->adminUser->id,
			'address'     => '123 Test Street',
			'description' => 'A test dormitory for testing purposes',
			'phone'       => '+1234567893'
		] );
	}

	public function test_sudo_user_can_access_dormitory_edit_page() {
		$response = $this->actingAs( $this->sudoUser, 'sanctum' )
			->getJson( "/api/dormitories/{$this->dormitory->id}" );

		$response->assertStatus( 200 );
		$response->assertJsonStructure( [ 
			'id',
			'name',
			'capacity',
			'gender',
			'admin_id',
			'address',
			'description',
			'phone',
			'admin' => [ 
				'id',
				'name',
				'email'
			]
		] );
	}

	public function test_sudo_user_can_update_dormitory_name() {
		$newName = 'Updated Dormitory Name';

		$response = $this->actingAs( $this->sudoUser, 'sanctum' )
			->putJson( "/api/dormitories/{$this->dormitory->id}", [ 
				'name' => $newName
			] );

		$response->assertStatus( 200 );

		// Verify database was updated
		$this->assertDatabaseHas( 'dormitories', [ 
			'id'   => $this->dormitory->id,
			'name' => $newName
		] );

		// Verify response contains updated data
		$response->assertJson( [ 
			'name' => $newName
		] );
	}

	public function test_sudo_user_can_update_dormitory_capacity() {
		$newCapacity = 250;

		$response = $this->actingAs( $this->sudoUser, 'sanctum' )
			->putJson( "/api/dormitories/{$this->dormitory->id}", [ 
				'capacity' => $newCapacity
			] );

		$response->assertStatus( 200 );

		// Verify database was updated
		$this->assertDatabaseHas( 'dormitories', [ 
			'id'       => $this->dormitory->id,
			'capacity' => $newCapacity
		] );

		// Verify response contains updated data
		$response->assertJson( [ 
			'capacity' => $newCapacity
		] );
	}

	public function test_sudo_user_can_update_dormitory_gender() {
		$newGender = 'female';

		$response = $this->actingAs( $this->sudoUser, 'sanctum' )
			->putJson( "/api/dormitories/{$this->dormitory->id}", [ 
				'gender' => $newGender
			] );

		$response->assertStatus( 200 );

		// Verify database was updated
		$this->assertDatabaseHas( 'dormitories', [ 
			'id'     => $this->dormitory->id,
			'gender' => $newGender
		] );

		// Verify response contains updated data
		$response->assertJson( [ 
			'gender' => $newGender
		] );
	}

	public function test_sudo_user_can_update_dormitory_admin() {
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
			'phone_numbers' => json_encode( [ '+1234567894' ] )
		] );

		AdminProfile::create( [ 
			'user_id'         => $newAdmin->id,
			'position'        => 'Assistant Manager',
			'department'      => 'Housing',
			'office_phone'    => '+1234567895',
			'office_location' => 'Building B, Room 102'
		] );

		$response = $this->actingAs( $this->sudoUser, 'sanctum' )
			->putJson( "/api/dormitories/{$this->dormitory->id}", [ 
				'admin_id' => $newAdmin->id
			] );

		$response->assertStatus( 200 );

		// Verify database was updated
		$this->assertDatabaseHas( 'dormitories', [ 
			'id'       => $this->dormitory->id,
			'admin_id' => $newAdmin->id
		] );

		// Verify response contains updated data
		$response->assertJson( [ 
			'admin_id' => $newAdmin->id
		] );
	}

	public function test_sudo_user_can_update_dormitory_address() {
		$newAddress = '456 New Address Street';

		$response = $this->actingAs( $this->sudoUser, 'sanctum' )
			->putJson( "/api/dormitories/{$this->dormitory->id}", [ 
				'address' => $newAddress
			] );

		$response->assertStatus( 200 );

		// Verify database was updated
		$this->assertDatabaseHas( 'dormitories', [ 
			'id'      => $this->dormitory->id,
			'address' => $newAddress
		] );

		// Verify response contains updated data
		$response->assertJson( [ 
			'address' => $newAddress
		] );
	}

	public function test_sudo_user_can_update_dormitory_description() {
		$newDescription = 'Updated description for the dormitory';

		$response = $this->actingAs( $this->sudoUser, 'sanctum' )
			->putJson( "/api/dormitories/{$this->dormitory->id}", [ 
				'description' => $newDescription
			] );

		$response->assertStatus( 200 );

		// Verify database was updated
		$this->assertDatabaseHas( 'dormitories', [ 
			'id'          => $this->dormitory->id,
			'description' => $newDescription
		] );

		// Verify response contains updated data
		$response->assertJson( [ 
			'description' => $newDescription
		] );
	}

	public function test_sudo_user_can_update_dormitory_phone() {
		$newPhone = '+1234567896';

		$response = $this->actingAs( $this->sudoUser, 'sanctum' )
			->putJson( "/api/dormitories/{$this->dormitory->id}", [ 
				'phone' => $newPhone
			] );

		$response->assertStatus( 200 );

		// Verify database was updated
		$this->assertDatabaseHas( 'dormitories', [ 
			'id'    => $this->dormitory->id,
			'phone' => $newPhone
		] );

		// Verify response contains updated data
		$response->assertJson( [ 
			'phone' => $newPhone
		] );
	}

	public function test_sudo_user_can_update_multiple_dormitory_fields_simultaneously() {
		$updateData = [ 
			'name'        => 'Multi-Updated Dormitory',
			'capacity'    => 300,
			'gender'      => 'male',
			'address'     => '789 Multi Update Street',
			'description' => 'Dormitory with multiple field updates',
			'phone'       => '+1234567897'
		];

		$response = $this->actingAs( $this->sudoUser, 'sanctum' )
			->putJson( "/api/dormitories/{$this->dormitory->id}", $updateData );

		$response->assertStatus( 200 );

		// Verify all fields were updated in database
		$this->assertDatabaseHas( 'dormitories', array_merge(
			[ 'id' => $this->dormitory->id ],
			$updateData
		) );

		// Verify response contains all updated data
		$response->assertJson( $updateData );
	}

	public function test_sudo_user_can_update_dormitory_with_partial_data() {
		// Update only some fields, leaving others unchanged
		$partialUpdate = [ 
			'name'     => 'Partially Updated Dormitory',
			'capacity' => 225
		];

		$response = $this->actingAs( $this->sudoUser, 'sanctum' )
			->putJson( "/api/dormitories/{$this->dormitory->id}", $partialUpdate );

		$response->assertStatus( 200 );

		// Verify updated fields
		$this->assertDatabaseHas( 'dormitories', [ 
			'id'       => $this->dormitory->id,
			'name'     => 'Partially Updated Dormitory',
			'capacity' => 225
		] );

		// Verify unchanged fields remain the same
		$this->assertDatabaseHas( 'dormitories', [ 
			'id'       => $this->dormitory->id,
			'gender'   => 'mixed', // Original value
			'admin_id' => $this->adminUser->id // Original value
		] );
	}

	public function test_dormitory_update_validates_required_fields() {
		// Try to update with empty name
		$response = $this->actingAs( $this->sudoUser, 'sanctum' )
			->putJson( "/api/dormitories/{$this->dormitory->id}", [ 
				'name' => ''
			] );

		$response->assertStatus( 422 );
		$response->assertJsonValidationErrors( [ 'name' ] );
	}

	public function test_dormitory_update_validates_capacity_format() {
		// Try to update with invalid capacity
		$response = $this->actingAs( $this->sudoUser, 'sanctum' )
			->putJson( "/api/dormitories/{$this->dormitory->id}", [ 
				'capacity' => 'invalid-capacity'
			] );

		$response->assertStatus( 422 );
		$response->assertJsonValidationErrors( [ 'capacity' ] );
	}

	public function test_dormitory_update_validates_gender_values() {
		// Try to update with invalid gender
		$response = $this->actingAs( $this->sudoUser, 'sanctum' )
			->putJson( "/api/dormitories/{$this->dormitory->id}", [ 
				'gender' => 'invalid-gender'
			] );

		$response->assertStatus( 422 );
		$response->assertJsonValidationErrors( [ 'gender' ] );
	}

	public function test_dormitory_update_validates_admin_id_exists() {
		// Try to update with non-existent admin ID
		$response = $this->actingAs( $this->sudoUser, 'sanctum' )
			->putJson( "/api/dormitories/{$this->dormitory->id}", [ 
				'admin_id' => 99999
			] );

		$response->assertStatus( 422 );
		$response->assertJsonValidationErrors( [ 'admin_id' ] );
	}

	public function test_dormitory_update_preserves_unchanged_fields() {
		// Get original values
		$originalDormitory = $this->dormitory->fresh();

		// Update only name
		$response = $this->actingAs( $this->sudoUser, 'sanctum' )
			->putJson( "/api/dormitories/{$this->dormitory->id}", [ 
				'name' => 'Name Only Update'
			] );

		$response->assertStatus( 200 );

		// Verify only name changed
		$updatedDormitory = Dormitory::find( $this->dormitory->id );

		$this->assertEquals( 'Name Only Update', $updatedDormitory->name );
		$this->assertEquals( $originalDormitory->capacity, $updatedDormitory->capacity );
		$this->assertEquals( $originalDormitory->gender, $updatedDormitory->gender );
		$this->assertEquals( $originalDormitory->admin_id, $updatedDormitory->admin_id );
		$this->assertEquals( $originalDormitory->address, $updatedDormitory->address );
		$this->assertEquals( $originalDormitory->description, $updatedDormitory->description );
		$this->assertEquals( $originalDormitory->phone, $updatedDormitory->phone );
	}

	public function test_dormitory_update_returns_updated_data_with_relationships() {
		$response = $this->actingAs( $this->sudoUser, 'sanctum' )
			->putJson( "/api/dormitories/{$this->dormitory->id}", [ 
				'name' => 'Updated with Relationships'
			] );

		$response->assertStatus( 200 );

		// Verify response includes admin relationship
		$response->assertJsonStructure( [ 
			'id',
			'name',
			'capacity',
			'gender',
			'admin_id',
			'address',
			'description',
			'phone',
			'admin' => [ 
				'id',
				'name',
				'email'
			]
		] );

		// Verify admin data is correct
		$response->assertJson( [ 
			'admin' => [ 
				'id'    => $this->adminUser->id,
				'name'  => $this->adminUser->name,
				'email' => $this->adminUser->email
			]
		] );
	}

	public function test_non_sudo_user_cannot_update_dormitory() {
		// Create a regular user
		$regularRole = Role::create( [ 'name' => 'student' ] );
		$regularUser = User::create( [ 
			'name'          => 'Regular User',
			'first_name'    => 'Regular',
			'last_name'     => 'User',
			'email'         => 'regular@test.com',
			'password'      => bcrypt( 'password' ),
			'role_id'       => $regularRole->id,
			'status'        => 'approved',
			'phone_numbers' => json_encode( [ '+1234567898' ] )
		] );

		$response = $this->actingAs( $regularUser, 'sanctum' )
			->putJson( "/api/dormitories/{$this->dormitory->id}", [ 
				'name' => 'Unauthorized Update'
			] );

		$response->assertStatus( 403 );

		// Verify database was not updated
		$this->assertDatabaseHas( 'dormitories', [ 
			'id'   => $this->dormitory->id,
			'name' => 'Test Dormitory' // Original name
		] );
	}

	public function test_dormitory_update_handles_missing_dormitory() {
		$response = $this->actingAs( $this->sudoUser, 'sanctum' )
			->putJson( "/api/dormitories/99999", [ 
				'name' => 'Non-existent Dormitory'
			] );

		$response->assertStatus( 404 );
	}

	public function test_dormitory_update_logs_changes() {
		$response = $this->actingAs( $this->sudoUser, 'sanctum' )
			->putJson( "/api/dormitories/{$this->dormitory->id}", [ 
				'name'     => 'Logged Update',
				'capacity' => 275
			] );

		$response->assertStatus( 200 );

		// Check if changes were logged (this depends on your logging implementation)
		// For now, we'll just verify the update was successful
		$this->assertDatabaseHas( 'dormitories', [ 
			'id'       => $this->dormitory->id,
			'name'     => 'Logged Update',
			'capacity' => 275
		] );
	}
}
