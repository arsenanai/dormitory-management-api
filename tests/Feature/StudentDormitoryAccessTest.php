<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Role;
use App\Models\SemesterPayment;
use App\Models\Dormitory;
use App\Models\Room;
use App\Models\Bed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StudentDormitoryAccessTest extends TestCase {
	use RefreshDatabase;

	protected function setUp(): void {
		parent::setUp();
		$this->seed();
	}

	#[Test]
	public function student_without_current_payment_cannot_access_dormitory() {
		// Create a student role
		$studentRole = Role::where( 'name', 'student' )->first();

		// Create a student without any semester payment
		$student = User::factory()->create( [ 
			'role_id' => $studentRole->id,
			'status'  => 'active'
		] );

		// Test dormitory access check via API
		$response = $this->actingAs( $student )->getJson( '/api/dormitory-access/check' );

		$response->assertStatus( 200 );
		$response->assertJson( [ 'can_access' => false ] );
	}

	#[Test]
	public function student_with_unapproved_payment_cannot_access_dormitory() {
		// Create a student role
		$studentRole = Role::where( 'name', 'student' )->first();

		// Create a student
		$student = User::factory()->create( [ 
			'role_id' => $studentRole->id,
			'status'  => 'active'
		] );

		// Create a semester payment that is not approved
		SemesterPayment::factory()->create( [ 
			'user_id'                   => $student->id,
			'semester'                  => SemesterPayment::getCurrentSemester(),
			'payment_approved'          => false,
			'dormitory_access_approved' => false,
			'payment_status'            => 'pending',
			'dormitory_status'          => 'pending'
		] );

		// Test dormitory access check via API
		$response = $this->actingAs( $student )->getJson( '/api/dormitory-access/check' );

		$response->assertStatus( 200 );
		$response->assertJson( [ 'can_access' => false ] );
	}

	#[Test]
	public function student_with_payment_approved_but_dormitory_not_approved_cannot_access() {
		// Create a student role
		$studentRole = Role::where( 'name', 'student' )->first();

		// Create a student
		$student = User::factory()->create( [ 
			'role_id' => $studentRole->id,
			'status'  => 'active'
		] );

		// Create a semester payment with payment approved but dormitory access not approved
		SemesterPayment::factory()->create( [ 
			'user_id'                   => $student->id,
			'semester'                  => SemesterPayment::getCurrentSemester(),
			'payment_approved'          => true,
			'dormitory_access_approved' => false,
			'payment_status'            => 'approved',
			'dormitory_status'          => 'pending'
		] );

		// Test dormitory access check via API
		$response = $this->actingAs( $student )->getJson( '/api/dormitory-access/check' );

		$response->assertStatus( 200 );
		$response->assertJson( [ 'can_access' => false ] );
	}

	#[Test]
	public function student_with_dormitory_approved_but_payment_not_approved_cannot_access() {
		// Create a student role
		$studentRole = Role::where( 'name', 'student' )->first();

		// Create a student
		$student = User::factory()->create( [ 
			'role_id' => $studentRole->id,
			'status'  => 'active'
		] );

		// Create a semester payment with dormitory access approved but payment not approved
		SemesterPayment::factory()->create( [ 
			'user_id'                   => $student->id,
			'semester'                  => SemesterPayment::getCurrentSemester(),
			'payment_approved'          => false,
			'dormitory_access_approved' => true,
			'payment_status'            => 'pending',
			'dormitory_status'          => 'approved'
		] );

		// Test dormitory access check via API
		$response = $this->actingAs( $student )->getJson( '/api/dormitory-access/check' );

		$response->assertStatus( 200 );
		$response->assertJson( [ 'can_access' => false ] );
	}

	#[Test]
	public function student_with_fully_approved_payment_can_access_dormitory() {
		// Create a student role
		$studentRole = Role::where( 'name', 'student' )->first();

		// Create a student
		$student = User::factory()->create( [ 
			'role_id' => $studentRole->id,
			'status'  => 'active'
		] );

		// Create a fully approved semester payment
		SemesterPayment::factory()->create( [ 
			'user_id'                   => $student->id,
			'semester'                  => SemesterPayment::getCurrentSemester(),
			'payment_approved'          => true,
			'dormitory_access_approved' => true,
			'payment_status'            => 'approved',
			'dormitory_status'          => 'approved'
		] );

		// Test dormitory access check via API
		$response = $this->actingAs( $student )->getJson( '/api/dormitory-access/check' );

		$response->assertStatus( 200 );
		$response->assertJson( [ 'can_access' => true ] );
	}

	#[Test]
	public function student_with_old_payment_cannot_access_dormitory() {
		// Create a student role
		$studentRole = Role::where( 'name', 'student' )->first();

		// Create a student
		$student = User::factory()->create( [ 
			'role_id' => $studentRole->id,
			'status'  => 'active'
		] );

		// Create an old semester payment (not current semester)
		$oldSemester = '2023-fall'; // Assuming current semester is 2024-fall or later
		SemesterPayment::factory()->create( [ 
			'user_id'                   => $student->id,
			'semester'                  => $oldSemester,
			'payment_approved'          => true,
			'dormitory_access_approved' => true,
			'payment_status'            => 'approved',
			'dormitory_status'          => 'approved'
		] );

		// Test dormitory access check via API
		$response = $this->actingAs( $student )->getJson( '/api/dormitory-access/check' );

		$response->assertStatus( 200 );
		$response->assertJson( [ 'can_access' => false ] );
	}

	#[Test]
	public function admin_can_always_access_dormitory_regardless_of_payment_status() {
		// Create an admin role
		$adminRole = Role::where( 'name', 'admin' )->first();

		// Create an admin user
		$admin = User::factory()->create( [ 
			'role_id' => $adminRole->id,
			'status'  => 'active'
		] );

		// Test dormitory access check via API
		$response = $this->actingAs( $admin )->getJson( '/api/dormitory-access/check' );

		$response->assertStatus( 200 );
		$response->assertJson( [ 'can_access' => true ] );
	}

	#[Test]
	public function sudo_can_always_access_dormitory_regardless_of_payment_status() {
		// Create a sudo role
		$sudoRole = Role::where( 'name', 'sudo' )->first();

		// Create a sudo user
		$sudo = User::factory()->create( [ 
			'role_id' => $sudoRole->id,
			'status'  => 'active'
		] );

		// Test dormitory access check via API
		$response = $this->actingAs( $sudo )->getJson( '/api/dormitory-access/check' );

		$response->assertStatus( 200 );
		$response->assertJson( [ 'can_access' => true ] );
	}

	#[Test]
	public function guest_with_approved_profile_can_access_dormitory() {
		// Create a guest role
		$guestRole = Role::where( 'name', 'guest' )->first();

		// Create a guest user
		$guest = User::factory()->create( [ 
			'role_id' => $guestRole->id,
			'status'  => 'active'
		] );

		// Create an approved guest profile
		$guest->guestProfile()->create( [ 
			'is_approved'      => true,
			'visit_start_date' => now()->subDay(),
			'visit_end_date'   => now()->addDay(),
			'daily_rate'       => 100.00
		] );

		// Test dormitory access check via API
		$response = $this->actingAs( $guest )->getJson( '/api/dormitory-access/check' );

		$response->assertStatus( 200 );
		$response->assertJson( [ 'can_access' => true ] );
	}

	#[Test]
	public function guest_without_approved_profile_cannot_access_dormitory() {
		// Create a guest role
		$guestRole = Role::where( 'name', 'guest' )->first();

		// Create a guest user
		$guest = User::factory()->create( [ 
			'role_id' => $guestRole->id,
			'status'  => 'active'
		] );

		// Create an unapproved guest profile
		$guest->guestProfile()->create( [ 
			'is_approved'      => false,
			'visit_start_date' => now()->subDay(),
			'visit_end_date'   => now()->addDay(),
			'daily_rate'       => 100.00
		] );

		// Test dormitory access check via API
		$response = $this->actingAs( $guest )->getJson( '/api/dormitory-access/check' );

		$response->assertStatus( 200 );
		$response->assertJson( [ 'can_access' => false ] );
	}

	#[Test]
	public function user_can_check_another_users_dormitory_access_with_proper_permissions() {
		// Create an admin role
		$adminRole = Role::where( 'name', 'admin' )->first();

		// Create an admin user
		$admin = User::factory()->create( [ 
			'role_id' => $adminRole->id,
			'status'  => 'active'
		] );

		// Create a student role
		$studentRole = Role::where( 'name', 'student' )->first();

		// Create a student without payment
		$student = User::factory()->create( [ 
			'role_id' => $studentRole->id,
			'status'  => 'active'
		] );

		// Admin checks student's dormitory access
		$response = $this->actingAs( $admin )->getJson( "/api/users/{$student->id}/can-access-dormitory" );

		$response->assertStatus( 200 );
		$response->assertJson( [ 'can_access' => false ] );
	}
}