<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\SemesterPayment;

class DormitoryAccessTest extends TestCase {
	use RefreshDatabase;

	/** @test */
	public function student_with_current_semester_payment_can_access_dormitory() {
		$student = User::factory()->create( [ 'role_id' => 3 ] ); // Assume 3 = student
		SemesterPayment::factory()->create( [ 
			'user_id'                   => $student->id,
			'semester'                  => '2024-fall',
			'payment_approved'          => true,
			'dormitory_access_approved' => true,
		] );

		$response = $this->actingAs( $student )->getJson( '/api/dormitory-access/check' );
		$response->assertStatus( 200 );
		$response->assertJson( [ 'can_access' => true ] );
	}

	/** @test */
	public function student_without_current_semester_payment_cannot_access_dormitory() {
		$student = User::factory()->create( [ 'role_id' => 3 ] ); // Assume 3 = student
		// No payment created

		$response = $this->actingAs( $student )->getJson( '/api/dormitory-access/check' );
		$response->assertStatus( 200 );
		$response->assertJson( [ 'can_access' => false ] );
	}
}