<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\SemesterPayment;
use PHPUnit\Framework\Attributes\Test;

class DormitoryAccessTest extends TestCase {
	use RefreshDatabase;

	protected function setUp(): void {
		parent::setUp();
		$this->seed();
	}

	#[Test]
	public function student_with_current_semester_payment_can_access_dormitory() {
		$studentRole = Role::where( 'name', 'student' )->first();
		$student = User::factory()->create( [ 'role_id' => $studentRole->id ] );
		$currentSemester = SemesterPayment::getCurrentSemester();
		SemesterPayment::factory()->create( [ 
			'user_id'                   => $student->id,
			'semester'                  => $currentSemester,
			'payment_approved'          => true,
			'dormitory_access_approved' => true,
		] );

		$response = $this->actingAs( $student )->getJson( '/api/dormitory-access/check' );
		$response->assertStatus( 200 );
		$response->assertJson( [ 'can_access' => true ] );
	}

	#[Test]
	public function student_without_current_semester_payment_cannot_access_dormitory() {
		$studentRole = Role::where( 'name', 'student' )->first();
		$student = User::factory()->create( [ 'role_id' => $studentRole->id ] );
		// No payment created

		$response = $this->actingAs( $student )->getJson( '/api/dormitory-access/check' );
		$response->assertStatus( 200 );
		$response->assertJson( [ 'can_access' => false ] );
	}
}