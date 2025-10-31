<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Room;
use App\Models\Dormitory;
use App\Models\StudentProfile;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

class BloodTypeTest extends TestCase {
	use RefreshDatabase, WithFaker;

	protected $admin;

	protected function setUp(): void {
		parent::setUp();
		// Create necessary roles and an admin user for authentication,
		// instead of running the full seeder which can cause data conflicts.
		$adminRole = Role::firstOrCreate(['name' => 'admin']);
		$sudoRole = Role::firstOrCreate(['name' => 'sudo']);
		Role::firstOrCreate(['name' => 'student']); // Ensure student role exists
		$this->admin = User::factory()->create([
			'email' => 'admin@email.com',
			'password' => bcrypt('supersecret'),
			'role_id' => $adminRole->id,
		]);
		// Admin needs a dormitory to be able to log in and perform actions.
		$dormitory = Dormitory::factory()->create(['admin_id' => $this->admin->id]);
		\App\Models\AdminProfile::factory()->create(['user_id' => $this->admin->id, 'dormitory_id' => $dormitory->id]);

		$this->seed(\Database\Seeders\BloodTypeSeeder::class);

		// Create a room so that Room::first() doesn't return null in tests
		Room::factory()->create(['dormitory_id' => $dormitory->id]);
	}

	private function loginAsAdmin() {
		$response = $this->postJson( '/api/login', [
			'email'    => 'admin@email.com',
			'password' => 'supersecret',
		] );
		return $response->json( 'token' );
	}

	#[Test]
	public function blood_types_are_available_in_api() {
		$token = $this->loginAsAdmin();
		$response = $this->getJson( "/api/blood-types", [ 
			'Authorization' => "Bearer $token"
		] );

		$response->assertStatus( 200 );

		$bloodTypes = $response->json( 'data' );
		$this->assertGreaterThan( 0, count( $bloodTypes ) );

		// Check for common blood types
		$bloodTypeNames = collect( $bloodTypes )->pluck( 'name' )->toArray();
		$this->assertContains( 'A+', $bloodTypeNames );
		$this->assertContains( 'A-', $bloodTypeNames );
		$this->assertContains( 'B+', $bloodTypeNames );
		$this->assertContains( 'B-', $bloodTypeNames );
		$this->assertContains( 'AB+', $bloodTypeNames );
		$this->assertContains( 'AB-', $bloodTypeNames );
		$this->assertContains( 'O+', $bloodTypeNames );
		$this->assertContains( 'O-', $bloodTypeNames );
	}

	#[Test]
	public function student_profile_can_have_blood_type() {
		$token = $this->loginAsAdmin();

		$response = $this->postJson( "/api/students", [ 
			'iin'                      => '123456789017',
			'first_name'               => 'David',
			'last_name'                => 'Wilson',
			'faculty'                  => 'engineering',
			'specialist'               => 'computer_sciences',
			'enrollment_year'          => 2024,
			'gender'                   => 'male',
			'email'                    => 'david@example.com',
			'password'                 => 'password123',
			'password_confirmation'    => 'password123',
			'room_id'                  => Room::first()->id,
			'blood_type'               => 'A+',
			'agree_to_dormitory_rules' => true,
			'has_meal_plan'            => false,
		], [ 
			'Authorization' => "Bearer $token"
		] );

		$response->assertStatus( 201 );

		$studentId = $response->json( 'id' );

		// Debug: Check what we actually got
		$this->assertNotNull( $studentId, 'Student ID should not be null' );

		$this->assertDatabaseHas( 'student_profiles', [ 
			'user_id'    => $studentId,
			'blood_type' => 'A+'
		] );
	}

	#[Test]
	public function blood_type_is_validated_when_creating_student_profile() {
		$token = $this->loginAsAdmin();

		$response = $this->postJson( "/api/students", [ 
			'iin'             => '123456789012',
			'name'            => 'John Doe',
			'faculty'         => 'engineering',
			'specialist'      => 'computer_sciences',
			'enrollment_year' => 2024,
			'gender'          => 'male',
			'email'           => 'john@example.com',
			'password'        => 'password123',
			'room_id'         => Room::first()->id,
			'blood_type'      => 'INVALID_TYPE'
		], [ 
			'Authorization' => "Bearer $token"
		] );

		$response->assertStatus( 422 )
			->assertJsonValidationErrors( [ 'blood_type' ] );
	}

	#[Test]
	public function valid_blood_type_is_accepted() {
		$token = $this->loginAsAdmin();

		$response = $this->postJson( "/api/students", [ 
			'iin'                      => '123456789014',
			'first_name'               => 'Jane',
			'last_name'                => 'Doe',
			'faculty'                  => 'engineering',
			'specialist'               => 'computer_sciences',
			'enrollment_year'          => 2024,
			'gender'                   => 'female',
			'email'                    => 'jane@example.com',
			'password'                 => 'password123',
			'password_confirmation'    => 'password123',
			'room_id'                  => Room::first()->id,
			'blood_type'               => 'A+',
			'agree_to_dormitory_rules' => true,
			'has_meal_plan'            => true,
		], [ 
			'Authorization' => "Bearer $token"
		] );

		$response->assertStatus( 201 );
	}

	#[Test]
	public function blood_type_can_be_updated() {
		$token = $this->loginAsAdmin();

		// First create a student
		$createResponse = $this->postJson( "/api/students", [ 
			'iin'                      => '123456789014',
			'first_name'               => 'Bob',
			'last_name'                => 'Smith',
			'faculty'                  => 'engineering',
			'specialist'               => 'computer_sciences',
			'enrollment_year'          => 2024,
			'gender'                   => 'male',
			'email'                    => 'bob@example.com',
			'password'                 => 'password123',
			'password_confirmation'    => 'password123',
			'room_id'                  => Room::first()->id,
			'blood_type'               => 'A+',
			'agree_to_dormitory_rules' => true,
			'has_meal_plan'            => false,
		], [ 
			'Authorization' => "Bearer $token"
		] );

		$createResponse->assertStatus( 201 );
		$studentId = $createResponse->json( 'id' );

		// Update the student's blood type
		$response = $this->putJson( "/api/students/{$studentId}", [ 
			'first_name' => 'Bob',
			'last_name'  => 'Smith',
			'email'      => 'bob@example.com',
			'blood_type' => 'B+'
		], [ 
			'Authorization' => "Bearer $token"
		] );

		$response->assertStatus( 200 );

		$this->assertDatabaseHas( 'student_profiles', [ 
			'user_id'    => $studentId,
			'blood_type' => 'B+'
		] );
	}

	#[Test]
	public function blood_type_is_optional() {
		$token = $this->loginAsAdmin();

		$response = $this->postJson( "/api/students", [ 
			'iin'                      => '123456789015',
			'first_name'               => 'Alice',
			'last_name'                => 'Johnson',
			'faculty'                  => 'engineering',
			'specialist'               => 'computer_sciences',
			'enrollment_year'          => 2024,
			'gender'                   => 'female',
			'email'                    => 'alice@example.com',
			'password'                 => 'password123',
			'password_confirmation'    => 'password123',
			'room_id'                  => Room::first()->id,
			'agree_to_dormitory_rules' => true,
			'has_meal_plan'            => true,
			// No blood_type provided
		], [ 
			'Authorization' => "Bearer $token"
		] );

		$response->assertStatus( 201 );
	}

	#[Test]
	public function blood_type_can_be_null() {
		$token = $this->loginAsAdmin();

		$response = $this->postJson( "/api/students", [ 
			'iin'                      => '123456789016',
			'first_name'               => 'Charlie',
			'last_name'                => 'Brown',
			'faculty'                  => 'engineering',
			'specialist'               => 'computer_sciences',
			'enrollment_year'          => 2024,
			'gender'                   => 'male',
			'email'                    => 'charlie@example.com',
			'password'                 => 'password123',
			'password_confirmation'    => 'password123',
			'room_id'                  => Room::first()->id,
			'blood_type'               => null,
			'agree_to_dormitory_rules' => true,
			'has_meal_plan'            => false,
		], [ 
			'Authorization' => "Bearer $token"
		] );

		$response->assertStatus( 201 );

		$studentId = $response->json( 'id' );
		$this->assertDatabaseHas( 'student_profiles', [ 
			'user_id'    => $studentId,
			'blood_type' => null
		] );
	}
}
