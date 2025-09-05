<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\SemesterPayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PaymentControllerTest extends TestCase {
	use RefreshDatabase;

	private User $sudo;
	private User $admin;
	private User $student;

	protected function setUp(): void {
		parent::setUp();

		// Create roles
		$sudoRole = Role::create( [ 'name' => 'sudo' ] );
		$adminRole = Role::create( [ 'name' => 'admin' ] );
		$studentRole = Role::create( [ 'name' => 'student' ] );

		// Create test users
		$this->sudo = User::factory()->create( [ 'role_id' => $sudoRole->id ] );
		$this->admin = User::factory()->create( [ 'role_id' => $adminRole->id ] );
		$this->student = User::factory()->create( [ 'role_id' => $studentRole->id ] );
	}

	/** @test */
	public function admin_can_view_payments() {
		$payment = SemesterPayment::factory()->create( [ 
			'user_id'          => $this->student->id,
			'semester'         => '2025-fall',
			'year'             => 2025,
			'semester_type'    => 'fall',
			'amount'           => 50000,
			'payment_approved' => true,
			'payment_status'   => 'approved',
		] );

		$response = $this->actingAs( $this->admin, 'sanctum' )
			->getJson( '/api/payments' );

		$response->assertStatus( 200 )
			->assertJsonStructure( [ 
				'data' => [ 
					'*' => [ 
						'id',
						'userId',
						'amount',
						'paymentStatus',
						'paymentDate',
						'user'
					]
				]
			] );
	}

	/** @test */
	public function admin_can_create_payment() {
		Storage::fake( 'public' );
		$receiptFile = UploadedFile::fake()->create( 'receipt.pdf', 1024 );

		$paymentData = [ 
			'user_id'          => $this->student->id,
			'semester'         => '2025-fall',
			'year'             => 2025,
			'semester_type'    => 'fall',
			'amount'           => 50000,
			'payment_approved' => true,
			'payment_status'   => 'approved',
			'contract_number'  => 'CONTRACT-2024-001',
			'contract_date'    => '2024-08-15',
			'payment_date'     => '2024-09-01',
			'payment_method'   => 'bank_transfer',
			'year'             => 2025,
			'semester_type'    => 'fall',
		];

		$response = $this->actingAs( $this->admin, 'sanctum' )
			->postJson( '/api/payments', $paymentData );

		$response->assertStatus( 201 )
			->assertJsonFragment( [ 
				'amount'          => '50000.00',
				'contract_number' => 'CONTRACT-2024-001',
				'payment_status'  => 'approved'
			] );

		$this->assertDatabaseHas( 'semester_payments', [ 
			'user_id'  => $this->student->id,
			'amount'   => 50000,
			'semester' => '2025-fall',
		] );
	}

	/** @test */
	public function admin_can_update_payment() {
		$payment = SemesterPayment::factory()->create( [ 
			'user_id'          => $this->student->id,
			'semester'         => '2025-fall',
			'year'             => 2025,
			'semester_type'    => 'fall',
			'amount'           => 50000,
			'payment_approved' => false,
			'payment_status'   => 'pending',
		] );

		$updateData = [ 
			'amount'           => 60000,
			'payment_status'   => 'approved',
			'payment_approved' => true,
		];

		$response = $this->actingAs( $this->admin, 'sanctum' )
			->putJson( "/api/payments/{$payment->id}", $updateData );

		$response->assertStatus( 200 )
			->assertJsonFragment( [ 
				'amount'           => '60000.00',
				'payment_status'   => 'approved',
				'payment_approved' => true,
			] );
	}

	/** @test */
	public function admin_can_delete_payment() {
		$payment = SemesterPayment::factory()->create( [ 
			'user_id'          => $this->student->id,
			'semester'         => '2025-fall',
			'year'             => 2025,
			'semester_type'    => 'fall',
			'amount'           => 50000,
			'payment_approved' => true,
			'payment_status'   => 'approved',
		] );

		$response = $this->actingAs( $this->admin, 'sanctum' )
			->deleteJson( "/api/payments/{$payment->id}" );

		$response->assertStatus( 200 );
		$this->assertDatabaseMissing( 'semester_payments', [ 'id' => $payment->id ] );
	}

	/** @test */
	public function payments_can_be_filtered_by_user() {
		$otherStudent = User::factory()->create( [ 'role_id' => Role::where( 'name', 'student' )->first()->id ] );

		SemesterPayment::factory()->create( [ 
			'user_id'          => $this->student->id,
			'semester'         => '2025-fall',
			'year'             => 2025,
			'semester_type'    => 'fall',
			'amount'           => 50000,
			'payment_approved' => true,
			'payment_status'   => 'approved',
		] );
		SemesterPayment::factory()->create( [ 
			'user_id'          => $otherStudent->id,
			'semester'         => '2025-fall',
			'year'             => 2025,
			'semester_type'    => 'fall',
			'amount'           => 50000,
			'payment_approved' => true,
			'payment_status'   => 'approved',
		] );

		$response = $this->actingAs( $this->admin, 'sanctum' )
			->getJson( "/api/payments?user_id={$this->student->id}" );

		$response->assertStatus( 200 );
		// Should only return payments for the specified user
	}

	/** @test */
	public function admin_can_export_payments() {
		// Create payments for different semesters to avoid unique constraint violation
		SemesterPayment::factory()->create( [ 
			'user_id'          => $this->student->id,
			'semester'         => '2025-fall',
			'year'             => 2025,
			'semester_type'    => 'fall',
			'amount'           => 50000,
			'payment_approved' => true,
			'payment_status'   => 'approved',
		] );

		SemesterPayment::factory()->create( [ 
			'user_id'          => $this->student->id,
			'semester'         => '2025-spring',
			'year'             => 2025,
			'semester_type'    => 'spring',
			'amount'           => 45000,
			'payment_approved' => true,
			'payment_status'   => 'approved',
		] );

		SemesterPayment::factory()->create( [ 
			'user_id'          => $this->student->id,
			'semester'         => '2024-fall',
			'year'             => 2024,
			'semester_type'    => 'fall',
			'amount'           => 48000,
			'payment_approved' => true,
			'payment_status'   => 'approved',
		] );

		$response = $this->actingAs( $this->admin, 'sanctum' )
			->getJson( '/api/payments/export' );

		$response->assertStatus( 200 )
			->assertHeader( 'Content-Type', 'text/csv; charset=UTF-8' );
	}

	/** @test */
	public function student_cannot_access_payments_endpoint() {
		$response = $this->actingAs( $this->student, 'sanctum' )
			->getJson( '/api/payments' );

		$response->assertStatus( 403 );
	}

	/** @test */
	public function validation_errors_for_invalid_payment_data() {
		$invalidData = [ 
			'user_id'       => 999999, // Non-existent user
			'amount'        => -100, // Negative amount
			'contract_date' => 'invalid-date',
		];

		$response = $this->actingAs( $this->admin, 'sanctum' )
			->postJson( '/api/payments', $invalidData );

		$response->assertStatus( 422 )
			->assertJsonValidationErrors( [ 'user_id', 'amount', 'contract_date' ] );
	}
}
