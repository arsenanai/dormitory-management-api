<?php

namespace Tests\Unit;

use App\Models\SemesterPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SemesterPaymentTest extends TestCase {
	use RefreshDatabase;

	public function test_payment_belongs_to_user() {
		$user = User::factory()->create();
		$payment = SemesterPayment::factory()->create( [ 'user_id' => $user->id ] );

		$this->assertInstanceOf( User::class, $payment->user );
		$this->assertEquals( $user->id, $payment->user->id );
	}

	public function test_payment_has_payment_approver_relationship() {
		$approver = User::factory()->create();
		$payment = SemesterPayment::factory()->create( [ 'payment_approved_by' => $approver->id ] );

		$this->assertInstanceOf( User::class, $payment->paymentApprover );
		$this->assertEquals( $approver->id, $payment->paymentApprover->id );
	}

	public function test_payment_has_dormitory_approver_relationship() {
		$approver = User::factory()->create();
		$payment = SemesterPayment::factory()->create( [ 'dormitory_approved_by' => $approver->id ] );

		$this->assertInstanceOf( User::class, $payment->dormitoryApprover );
		$this->assertEquals( $approver->id, $payment->dormitoryApprover->id );
	}

	public function test_can_access_dormitory_returns_true_when_both_approved() {
		$payment = SemesterPayment::factory()->create( [ 
			'payment_approved'          => true,
			'dormitory_access_approved' => true
		] );

		$this->assertTrue( $payment->canAccessDormitory() );
	}

	public function test_can_access_dormitory_returns_false_when_payment_not_approved() {
		$payment = SemesterPayment::factory()->create( [ 
			'payment_approved'          => false,
			'dormitory_access_approved' => true
		] );

		$this->assertFalse( $payment->canAccessDormitory() );
	}

	public function test_can_access_dormitory_returns_false_when_dormitory_not_approved() {
		$payment = SemesterPayment::factory()->create( [ 
			'payment_approved'          => true,
			'dormitory_access_approved' => false
		] );

		$this->assertFalse( $payment->canAccessDormitory() );
	}

	public function test_get_current_semester_returns_correct_semester() {
		// This test assumes we're in a specific month - in a real scenario,
		// you'd want to mock the current time
		$currentSemester = SemesterPayment::getCurrentSemester();
		$this->assertIsString( $currentSemester );
		$this->assertMatchesRegularExpression( '/^\d{4}-(fall|spring|summer)$/', $currentSemester );
	}

	public function test_is_current_semester_returns_true_for_current_semester() {
		$currentSemester = SemesterPayment::getCurrentSemester();
		$payment = SemesterPayment::factory()->create( [ 'semester' => $currentSemester ] );

		$this->assertTrue( $payment->isCurrentSemester() );
	}

	public function test_is_current_semester_returns_false_for_different_semester() {
		$payment = SemesterPayment::factory()->create( [ 'semester' => '2024-fall' ] );

		// This will be false unless we're actually in fall 2024
		$this->assertFalse( $payment->isCurrentSemester() );
	}

	public function test_current_semester_scope_filters_correctly() {
		$currentSemester = SemesterPayment::getCurrentSemester();

		SemesterPayment::factory()->create( [ 'semester' => $currentSemester ] );
		SemesterPayment::factory()->create( [ 'semester' => '2024-fall' ] );
		SemesterPayment::factory()->create( [ 'semester' => '2024-spring' ] );

		$currentSemesterPayments = SemesterPayment::currentSemester()->get();

		$this->assertCount( 1, $currentSemesterPayments );
		$this->assertEquals( $currentSemester, $currentSemesterPayments->first()->semester );
	}

	public function test_approved_scope_filters_correctly() {
		SemesterPayment::factory()->create( [ 
			'payment_approved'          => true,
			'dormitory_access_approved' => true
		] );

		SemesterPayment::factory()->create( [ 
			'payment_approved'          => false,
			'dormitory_access_approved' => true
		] );

		SemesterPayment::factory()->create( [ 
			'payment_approved'          => true,
			'dormitory_access_approved' => false
		] );

		$approvedPayments = SemesterPayment::approved()->get();

		$this->assertCount( 1, $approvedPayments );
		$this->assertTrue( $approvedPayments->first()->payment_approved );
		$this->assertTrue( $approvedPayments->first()->dormitory_access_approved );
	}
}
