<?php

namespace Tests\Unit;

use App\Models\GuestProfile;
use App\Models\Role;
use App\Models\SemesterPayment;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserDormitoryAccessTest extends TestCase {
	use RefreshDatabase;

	public function test_user_has_student_profile_relationship() {
		$user = User::factory()->create();
		$profile = StudentProfile::factory()->create( [ 'user_id' => $user->id ] );

		$this->assertInstanceOf( StudentProfile::class, $user->studentProfile );
		$this->assertEquals( $profile->id, $user->studentProfile->id );
	}

	public function test_user_has_guest_profile_relationship() {
		$user = User::factory()->create();
		$profile = GuestProfile::factory()->create( [ 'user_id' => $user->id ] );

		$this->assertInstanceOf( GuestProfile::class, $user->guestProfile );
		$this->assertEquals( $profile->id, $user->guestProfile->id );
	}

	public function test_user_has_payments_relationship() {
		$user = User::factory()->create();
		$payment = SemesterPayment::factory()->create( [ 'user_id' => $user->id ] );

		$this->assertCount( 1, $user->semesterPayments );
		$this->assertEquals( $payment->id, $user->semesterPayments->first()->id );
	}

	public function test_user_has_current_payment_relationship() {
		$user = User::factory()->create();

		// Create payment for current semester
		$currentPayment = SemesterPayment::factory()->currentSemester()->create( [ 'user_id' => $user->id ] );

		// Create payment for different semester
		SemesterPayment::factory()->create( [ 
			'user_id'  => $user->id,
			'semester' => '2024-fall'
		] );

		$this->assertNotNull( $user->currentSemesterPayment );
		$this->assertEquals( $currentPayment->id, $user->currentSemesterPayment->id );
	}

	public function test_student_can_access_dormitory_when_payments_approved() {
		$studentRole = Role::factory()->create( [ 'name' => 'student' ] );
		$user = User::factory()->create( [ 'role_id' => $studentRole->id ] );

		SemesterPayment::factory()->currentSemester()->approved()->create( [ 'user_id' => $user->id ] );

		$this->assertTrue( $user->canAccessDormitory() );
	}

	public function test_student_cannot_access_dormitory_when_payment_not_approved() {
		$studentRole = Role::factory()->create( [ 'name' => 'student' ] );
		$user = User::factory()->create( [ 'role_id' => $studentRole->id ] );

		SemesterPayment::factory()->currentSemester()->create( [ 
			'user_id'                   => $user->id,
			'payment_approved'          => false,
			'dormitory_access_approved' => true
		] );

		$this->assertFalse( $user->canAccessDormitory() );
	}

	public function test_student_cannot_access_dormitory_when_dormitory_access_not_approved() {
		$studentRole = Role::factory()->create( [ 'name' => 'student' ] );
		$user = User::factory()->create( [ 'role_id' => $studentRole->id ] );

		SemesterPayment::factory()->currentSemester()->create( [ 
			'user_id'                   => $user->id,
			'payment_approved'          => true,
			'dormitory_access_approved' => false
		] );

		$this->assertFalse( $user->canAccessDormitory() );
	}

	public function test_student_cannot_access_dormitory_when_no_current_payment() {
		$studentRole = Role::factory()->create( [ 'name' => 'student' ] );
		$user = User::factory()->create( [ 'role_id' => $studentRole->id ] );

		$this->assertFalse( $user->canAccessDormitory() );
	}

	public function test_guest_can_access_dormitory_when_authorized() {
		$guestRole = Role::factory()->create( [ 'name' => 'guest' ] );
		$user = User::factory()->create( [ 'role_id' => $guestRole->id ] );

		GuestProfile::factory()->create( [ 
			'user_id'          => $user->id,
			'is_approved'      => true,
			'visit_start_date' => now()->subDay(),
			'visit_end_date'   => now()->addDay()
		] );

		$this->assertTrue( $user->canAccessDormitory() );
	}

	public function test_guest_cannot_access_dormitory_when_not_approved() {
		$guestRole = Role::factory()->create( [ 'name' => 'guest' ] );
		$user = User::factory()->create( [ 'role_id' => $guestRole->id ] );

		GuestProfile::factory()->create( [ 
			'user_id'          => $user->id,
			'is_approved'      => false,
			'visit_start_date' => now()->subDay(),
			'visit_end_date'   => now()->addDay()
		] );

		$this->assertFalse( $user->canAccessDormitory() );
	}

	public function test_admin_can_always_access_dormitory() {
		$adminRole = Role::factory()->create( [ 'name' => 'admin' ] );
		$user = User::factory()->create( [ 'role_id' => $adminRole->id ] );

		$this->assertTrue( $user->canAccessDormitory() );
	}

	public function test_sudo_can_always_access_dormitory() {
		$sudoRole = Role::factory()->create( [ 'name' => 'sudo' ] );
		$user = User::factory()->create( [ 'role_id' => $sudoRole->id ] );

		$this->assertTrue( $user->canAccessDormitory() );
	}

	public function test_visitor_can_always_access_dormitory() {
		$visitorRole = Role::factory()->create( [ 'name' => 'visitor' ] );
		$user = User::factory()->create( [ 'role_id' => $visitorRole->id ] );

		$this->assertTrue( $user->canAccessDormitory() );
	}
}
