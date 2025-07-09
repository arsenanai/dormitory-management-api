<?php

namespace Tests\Unit;

use App\Models\StudentProfile;
use App\Models\SemesterPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentProfileTest extends TestCase {
	use RefreshDatabase;

	public function test_student_profile_belongs_to_user() {
		$user = User::factory()->create();
		$profile = StudentProfile::factory()->create( [ 'user_id' => $user->id ] );

		$this->assertInstanceOf( User::class, $profile->user );
		$this->assertEquals( $user->id, $profile->user->id );
	}

	public function test_student_profile_has_blood_type_field() {
		$profile = StudentProfile::factory()->create( [ 'blood_type' => 'A+' ] );

		$this->assertEquals( 'A+', $profile->blood_type );
	}

	public function test_student_profile_has_emergency_contact_fields() {
		$profile = StudentProfile::factory()->create( [ 
			'emergency_contact_name'         => 'John Doe',
			'emergency_contact_phone'        => '+1234567890',
			'emergency_contact_relationship' => 'Father'
		] );

		$this->assertEquals( 'John Doe', $profile->emergency_contact_name );
		$this->assertEquals( '+1234567890', $profile->emergency_contact_phone );
		$this->assertEquals( 'Father', $profile->emergency_contact_relationship );
	}

	public function test_student_profile_has_medical_and_dietary_info() {
		$profile = StudentProfile::factory()->create( [ 
			'medical_conditions'   => 'Asthma',
			'dietary_restrictions' => 'Vegetarian'
		] );

		$this->assertEquals( 'Asthma', $profile->medical_conditions );
		$this->assertEquals( 'Vegetarian', $profile->dietary_restrictions );
	}

	public function test_has_current_semester_access_returns_false_when_no_payment() {
		$profile = StudentProfile::factory()->create();

		$this->assertFalse( $profile->hasCurrentSemesterAccess() );
	}

	public function test_has_current_semester_access_returns_true_when_payment_approved() {
		$user = User::factory()->create();
		$profile = StudentProfile::factory()->create( [ 'user_id' => $user->id ] );

		SemesterPayment::factory()->create( [ 
			'user_id'                   => $user->id,
			'semester'                  => SemesterPayment::getCurrentSemester(),
			'payment_approved'          => true,
			'dormitory_access_approved' => true
		] );

		$this->assertTrue( $profile->hasCurrentSemesterAccess() );
	}

	public function test_has_current_semester_access_returns_false_when_payment_not_approved() {
		$user = User::factory()->create();
		$profile = StudentProfile::factory()->create( [ 'user_id' => $user->id ] );

		SemesterPayment::factory()->create( [ 
			'user_id'                   => $user->id,
			'semester'                  => SemesterPayment::getCurrentSemester(),
			'payment_approved'          => false,
			'dormitory_access_approved' => true
		] );

		$this->assertFalse( $profile->hasCurrentSemesterAccess() );
	}

	public function test_has_current_semester_access_returns_false_when_dormitory_not_approved() {
		$user = User::factory()->create();
		$profile = StudentProfile::factory()->create( [ 'user_id' => $user->id ] );

		SemesterPayment::factory()->create( [ 
			'user_id'                   => $user->id,
			'semester'                  => SemesterPayment::getCurrentSemester(),
			'payment_approved'          => true,
			'dormitory_access_approved' => false
		] );

		$this->assertFalse( $profile->hasCurrentSemesterAccess() );
	}
}
