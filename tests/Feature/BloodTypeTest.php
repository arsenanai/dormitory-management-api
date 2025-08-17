<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\StudentProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

class BloodTypeTest extends TestCase {
	use RefreshDatabase, WithFaker;

	protected $admin;

	protected function setUp(): void {
		parent::setUp();
		$this->seed();
	}

	private function loginAsAdmin() {
		$response = $this->postJson( '/api/login', [ 
			'email'    => 'admin@email.com',
			'password' => 'supersecret',
		] );
		return $response->json( 'token' );
	}

	/** @test */
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

	/** @test */
	public function student_profile_can_have_blood_type() {
		$token = $this->loginAsAdmin();

		$response = $this->postJson( "/api/students", [ 
			'iin'             => '123456789017',
			'name'            => 'David Wilson',
			'faculty'         => 'engineering',
			'specialist'      => 'computer_sciences',
			'enrollment_year' => 2024,
			'gender'          => 'male',
			'email'           => 'david@example.com',
			'password'        => 'password123',
			'blood_type'      => 'A+'
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

	/** @test */
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
			'blood_type'      => 'INVALID_TYPE'
		], [ 
			'Authorization' => "Bearer $token"
		] );

		$response->assertStatus( 422 )
			->assertJsonValidationErrors( [ 'blood_type' ] );
	}

	/** @test */
	public function valid_blood_type_is_accepted() {
		$token = $this->loginAsAdmin();

		$response = $this->postJson( "/api/students", [ 
			'iin'             => '123456789013',
			'name'            => 'Jane Doe',
			'faculty'         => 'engineering',
			'specialist'      => 'computer_sciences',
			'enrollment_year' => 2024,
			'gender'          => 'female',
			'email'           => 'jane@example.com',
			'password'        => 'password123',
			'blood_type'      => 'A+'
		], [ 
			'Authorization' => "Bearer $token"
		] );

		$response->assertStatus( 201 );
	}

	/** @test */
	public function blood_type_can_be_updated() {
		$token = $this->loginAsAdmin();

		// First create a student
		$createResponse = $this->postJson( "/api/students", [ 
			'iin'             => '123456789014',
			'name'            => 'Bob Smith',
			'faculty'         => 'engineering',
			'specialist'      => 'computer_sciences',
			'enrollment_year' => 2024,
			'gender'          => 'male',
			'email'           => 'bob@example.com',
			'password'        => 'password123',
			'blood_type'      => 'A+'
		], [ 
			'Authorization' => "Bearer $token"
		] );

		$createResponse->assertStatus( 201 );
		$studentId = $createResponse->json( 'id' );

		// Update the student's blood type
		$response = $this->putJson( "/api/students/{$studentId}", [ 
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

	/** @test */
	public function blood_type_is_optional() {
		$token = $this->loginAsAdmin();

		$response = $this->postJson( "/api/students", [ 
			'iin'             => '123456789015',
			'name'            => 'Alice Johnson',
			'faculty'         => 'engineering',
			'specialist'      => 'computer_sciences',
			'enrollment_year' => 2024,
			'gender'          => 'female',
			'email'           => 'alice@example.com',
			'password'        => 'password123'
			// No blood_type provided
		], [ 
			'Authorization' => "Bearer $token"
		] );

		$response->assertStatus( 201 );
	}

	/** @test */
	public function blood_type_can_be_null() {
		$token = $this->loginAsAdmin();

		$response = $this->postJson( "/api/students", [ 
			'iin'             => '123456789016',
			'name'            => 'Charlie Brown',
			'faculty'         => 'engineering',
			'specialist'      => 'computer_sciences',
			'enrollment_year' => 2024,
			'gender'          => 'male',
			'email'           => 'charlie@example.com',
			'password'        => 'password123',
			'blood_type'      => null
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
