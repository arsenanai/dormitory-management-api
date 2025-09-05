<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\Role;
use App\Models\StudentProfile;
use App\Models\GuestProfile;
use App\Models\SemesterPayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserModelTest extends TestCase {
	use RefreshDatabase;

	public function test_user_belongs_to_role() {
		$role = Role::factory()->create( [ 'name' => 'student' ] );
		$user = User::factory()->create( [ 'role_id' => $role->id ] );

		$this->assertInstanceOf( Role::class, $user->role );
		$this->assertEquals( 'student', $user->role->name );
	}

	public function test_user_has_role_method() {
		$role = Role::factory()->create( [ 'name' => 'student' ] );
		$user = User::factory()->create( [ 'role_id' => $role->id ] );

		$this->assertTrue( $user->hasRole( 'student' ) );
		$this->assertFalse( $user->hasRole( 'admin' ) );
	}

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

	public function test_user_has_semester_payments_relationship() {
		$user = User::factory()->create();
		$payment = SemesterPayment::factory()->create( [ 'user_id' => $user->id ] );

		$this->assertInstanceOf( SemesterPayment::class, $user->semesterPayments->first() );
		$this->assertEquals( $payment->id, $user->semesterPayments->first()->id );
	}

	public function test_user_can_access_dormitory_for_student_with_approved_payment() {
		$role = Role::factory()->create( [ 'name' => 'student' ] );
		$user = User::factory()->create( [ 'role_id' => $role->id ] );

		SemesterPayment::factory()->create( [ 
			'user_id'                   => $user->id,
			'semester'                  => SemesterPayment::getCurrentSemester(),
			'payment_approved'          => true,
			'dormitory_access_approved' => true
		] );

		$this->assertTrue( $user->canAccessDormitory() );
	}

	public function test_user_cannot_access_dormitory_for_student_without_approved_payment() {
		$role = Role::factory()->create( [ 'name' => 'student' ] );
		$user = User::factory()->create( [ 'role_id' => $role->id ] );

		SemesterPayment::factory()->create( [ 
			'user_id'                   => $user->id,
			'semester'                  => SemesterPayment::getCurrentSemester(),
			'payment_approved'          => false,
			'dormitory_access_approved' => true
		] );

		$this->assertFalse( $user->canAccessDormitory() );
	}

	public function test_user_can_access_dormitory_for_approved_guest() {
		$role = Role::factory()->create( [ 'name' => 'guest' ] );
		$user = User::factory()->create( [ 'role_id' => $role->id ] );

		GuestProfile::factory()->create( [ 
			'user_id'          => $user->id,
			'is_approved'      => true,
			'visit_start_date' => now()->subDay(),
			'visit_end_date'   => now()->addDay()
		] );

		$this->assertTrue( $user->canAccessDormitory() );
	}

	public function test_user_can_access_dormitory_for_admin_role() {
		$role = Role::factory()->create( [ 'name' => 'admin' ] );
		$user = User::factory()->create( [ 'role_id' => $role->id ] );

		$this->assertTrue( $user->canAccessDormitory() );
	}

	public function test_user_can_access_dormitory_for_sudo_role() {
		$role = Role::factory()->create( [ 'name' => 'sudo' ] );
		$user = User::factory()->create( [ 'role_id' => $role->id ] );

		$this->assertTrue( $user->canAccessDormitory() );
	}

	public function test_user_fillable_fields_are_correct() {
		$user = new User();
		$expectedFillable = [ 
			'iin', 'name', 'first_name', 'last_name', 'email', 'email_verified_at', 'phone_numbers', 'room_id', 'dormitory_id', 'password', 'status', 'role_id', 'remember_token'
		];
		$this->assertEquals( $expectedFillable, $user->getFillable() );
	}

	public function test_user_casts_are_correct() {
		$user = new User();
		$expectedCasts = [ 
			'id'                => 'int',
			'email_verified_at' => 'datetime',
			'password'          => 'hashed',
			'phone_numbers'     => 'array',
		];
		$this->assertEquals( $expectedCasts, $user->getCasts() );
	}

	public function test_user_can_be_created_with_basic_fields() {
		$userData = [ 
			'iin'      => '123456789012',
			'name'     => 'John Doe',
			'email'    => 'john@example.com',
			'password' => 'password123',
		];

		$user = User::create( $userData );

		$this->assertInstanceOf( User::class, $user );
		$this->assertEquals( 'John Doe', $user->name );
		$this->assertEquals( 'john@example.com', $user->email );
	}
}
