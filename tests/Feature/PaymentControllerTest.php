<?php

namespace Tests\Feature;

use App\Models\Payment;use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\Test;
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

	#[Test]
	public function admin_can_view_payments() {
		Payment::factory()->create( [ 
			'user_id'          => $this->student->id,
			'amount'           => 50000,
			'deal_number'      => 'DEAL-123',
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
						'dealNumber',
						'dealDate',
						'user'
					]
				]
			] );
	}

	#[Test]
	public function admin_can_create_payment() {
		Storage::fake( 'public' );
		$receiptFile = UploadedFile::fake()->create( 'receipt.pdf', 1024 );

		$paymentData = [ 
			'user_id'       => $this->student->id,
			'amount'        => 50000,
			'deal_number'   => 'DEAL-2024-001',
			'deal_date'     => '2024-08-15',
			'date_from'     => '2024-09-01',
			'date_to'       => '2025-01-31',
			'payment_check' => $receiptFile,
		];

		$response = $this->actingAs( $this->admin, 'sanctum' )
			->postJson( '/api/payments', $paymentData );

		$response->assertStatus( 201 )
			->assertJsonFragment( [ 
				'amount'      => '50000.00',
				'deal_number' => 'DEAL-2024-001',
			] );

		$this->assertDatabaseHas( 'payments', [ 
			'user_id'     => $this->student->id,
			'amount'      => 50000,
			'deal_number' => 'DEAL-2024-001',
		] );
	}

	#[Test]
	public function admin_can_update_payment() {
		$payment = Payment::factory()->create( [ 
			'user_id'     => $this->student->id,
			'amount'      => 50000,
			'deal_number' => 'OLD-DEAL',
		] );

		$updateData = [ 
			'amount'      => 60000,
			'deal_number' => 'NEW-DEAL',
		];

		$response = $this->actingAs( $this->admin, 'sanctum' )
			->putJson( "/api/payments/{$payment->id}", $updateData );

		$response->assertStatus( 200 )
			->assertJsonFragment( [ 
				'amount'      => '60000.00',
				'deal_number' => 'NEW-DEAL',
			] );
	}

	#[Test]
	public function admin_can_delete_payment() {
		$payment = Payment::factory()->create( [ 
			'user_id' => $this->student->id,
			'amount'  => 50000,
		] );

		$response = $this->actingAs( $this->admin, 'sanctum' )
			->deleteJson( "/api/payments/{$payment->id}" );

		$response->assertStatus( 200 );
		$this->assertDatabaseMissing( 'payments', [ 'id' => $payment->id ] );
	}

	#[Test]
	public function payments_can_be_filtered_by_user() {
		$otherStudent = User::factory()->create( [ 'role_id' => Role::where( 'name', 'student' )->first()->id ] );

		Payment::factory()->create( [ 
			'user_id' => $this->student->id,
			'amount'  => 50000,
		] );
		Payment::factory()->create( [ 
			'user_id' => $otherStudent->id,
			'amount'  => 50000,
		] );

		$response = $this->actingAs( $this->admin, 'sanctum' )
			->getJson( "/api/payments?user_id={$this->student->id}" );

		$response->assertStatus( 200 );
		// Should only return payments for the specified user
	}

	#[Test]
	public function admin_can_export_payments() {
		Payment::factory()->create( [ 
			'user_id' => $this->student->id,
			'amount'  => 50000,
		] );

		Payment::factory()->create( [ 
			'user_id' => $this->student->id,
			'amount'  => 45000,
		] );

		Payment::factory()->create( [ 
			'user_id' => $this->student->id,
			'amount'  => 48000,
		] );

		$response = $this->actingAs( $this->admin, 'sanctum' )
			->getJson( '/api/payments/export' );

		$response->assertStatus( 200 )
			->assertHeader( 'Content-Type', 'text/csv; charset=UTF-8' );
	}

	#[Test]
	public function student_cannot_access_payments_endpoint() {
		$response = $this->actingAs( $this->student, 'sanctum' )
			->getJson( '/api/payments' );

		$response->assertStatus( 403 );
	}
	
	#[Test]
	public function validation_errors_for_invalid_payment_data() {
		$invalidData = [ 
			'user_id'   => 999999, // Non-existent user
			'amount'    => -100, // Negative amount
			'deal_date' => 'invalid-date',
		];

		$response = $this->actingAs( $this->admin, 'sanctum' )
			->postJson( '/api/payments', $invalidData );

		$response->assertStatus( 422 )
			->assertJsonValidationErrors( [ 'user_id', 'amount', 'deal_date' ] );
	}
}
