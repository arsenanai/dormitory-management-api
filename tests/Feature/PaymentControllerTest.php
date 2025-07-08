<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\Payment;
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
		$payment = Payment::factory()->create( [ 
			'user_id' => $this->student->id,
		] );

		$response = $this->actingAs( $this->admin, 'sanctum' )
			->getJson( '/api/payments' );

		$response->assertStatus( 200 )
			->assertJsonStructure( [ 
				'data' => [ 
					'*' => [ 
						'id',
						'user_id',
						'amount',
						'status',
						'payment_date',
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
			'user_id'         => $this->student->id,
			'amount'          => 50000,
			'contract_number' => 'CONTRACT-2024-001',
			'contract_date'   => '2024-01-01',
			'payment_date'    => '2024-01-15',
			'payment_method'  => 'Bank Transfer',
			'receipt_file'    => $receiptFile,
			'status'          => 'completed',
		];

		$response = $this->actingAs( $this->admin, 'sanctum' )
			->postJson( '/api/payments', $paymentData );

		$response->assertStatus( 201 )
			->assertJsonFragment( [ 
				'amount'          => 50000,
				'contract_number' => 'CONTRACT-2024-001',
				'status'          => 'completed'
			] );

		$this->assertDatabaseHas( 'payments', [ 
			'user_id'         => $this->student->id,
			'amount'          => 50000,
			'contract_number' => 'CONTRACT-2024-001'
		] );
	}

	/** @test */
	public function admin_can_update_payment() {
		$payment = Payment::factory()->create( [ 
			'user_id' => $this->student->id,
			'amount'  => 50000,
			'status'  => 'pending'
		] );

		$updateData = [ 
			'amount' => 60000,
			'status' => 'completed'
		];

		$response = $this->actingAs( $this->admin, 'sanctum' )
			->putJson( "/api/payments/{$payment->id}", $updateData );

		$response->assertStatus( 200 )
			->assertJsonFragment( [ 
				'amount' => 60000,
				'status' => 'completed'
			] );
	}

	/** @test */
	public function admin_can_delete_payment() {
		$payment = Payment::factory()->create( [ 
			'user_id' => $this->student->id,
		] );

		$response = $this->actingAs( $this->admin, 'sanctum' )
			->deleteJson( "/api/payments/{$payment->id}" );

		$response->assertStatus( 204 );
		$this->assertDatabaseMissing( 'payments', [ 'id' => $payment->id ] );
	}

	/** @test */
	public function payments_can_be_filtered_by_user() {
		$otherStudent = User::factory()->create( [ 'role_id' => Role::where( 'name', 'student' )->first()->id ] );

		Payment::factory()->create( [ 'user_id' => $this->student->id ] );
		Payment::factory()->create( [ 'user_id' => $otherStudent->id ] );

		$response = $this->actingAs( $this->admin, 'sanctum' )
			->getJson( "/api/payments?user_id={$this->student->id}" );

		$response->assertStatus( 200 );
		// Should only return payments for the specified user
	}

	/** @test */
	public function admin_can_export_payments() {
		Payment::factory()->count( 3 )->create( [ 
			'user_id' => $this->student->id,
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
