<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Dormitory;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\SemesterPayment;
use App\Models\Message;
use App\Models\Bed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

class DashboardControllerTest extends TestCase {
	use RefreshDatabase, WithFaker;

	private User $admin;
	private User $student;
	private User $guard;
	private Dormitory $dormitory;
	private Room $room;
	private RoomType $roomType;

	protected function setUp(): void {
		parent::setUp();

		// Create roles
		$adminRole = Role::create( [ 'name' => 'admin' ] );
		$studentRole = Role::create( [ 'name' => 'student' ] );
		$guardRole = Role::create( [ 'name' => 'guard' ] );

		// Create test users
		$this->admin = User::factory()->create( [ 
			'role_id'  => $adminRole->id,
			'email'    => 'admin@test.com',
			'password' => bcrypt( 'password123' ),
		] );

		$this->student = User::factory()->create( [ 
			'role_id'  => $studentRole->id,
			'email'    => 'student@test.com',
			'password' => bcrypt( 'password123' ),
		] );

		$this->guard = User::factory()->create( [ 
			'role_id'  => $guardRole->id,
			'email'    => 'guard@test.com',
			'password' => bcrypt( 'password123' ),
		] );

		// Create test dormitory and room structure
		$this->dormitory = Dormitory::factory()->create( [ 
			'name'    => 'Test Dormitory',
			'address' => '123 Test Street',
			'phone'   => '+1234567890',
		] );

		$this->roomType = RoomType::factory()->create( [ 
			'name'     => 'Single Room',
			'capacity' => 1,
			'price'    => 50000,
		] );

		$this->room = Room::factory()->create( [ 
			'dormitory_id' => $this->dormitory->id,
			'room_type_id' => $this->roomType->id,
			'number'       => '101',
			'floor'        => 1,
			'is_occupied'  => true,
		] );

		// Create beds for the room
		Bed::factory()->count( 2 )->create( [ 
			'room_id'     => $this->room->id,
			'bed_number'  => 1,
			'is_occupied' => true,
		] );
	}

	public function test_admin_can_view_dashboard_stats() {
		// Create additional test data
		$student2 = User::factory()->create( [ 'role_id' => $this->student->role_id ] );
		$student3 = User::factory()->create( [ 'role_id' => $this->student->role_id ] );

		// Create payments
		SemesterPayment::factory()->create( [ 
			'user_id'        => $this->student->id,
			'semester'       => '2025-fall',
			'year'           => 2025,
			'semester_type'  => 'fall',
			'amount'         => 50000,
			'payment_approved' => true,
			'payment_status'   => 'approved',
		] );

		SemesterPayment::factory()->create( [ 
			'user_id'        => $student2->id,
			'semester'       => '2025-fall',
			'year'           => 2025,
			'semester_type'  => 'fall',
			'amount'         => 50000,
			'payment_approved' => false,
			'payment_status'   => 'pending',
		] );

		// Create messages
		Message::factory()->count( 5 )->create( [ 
			'sender_id'   => $this->student->id,
			'receiver_id' => $this->admin->id,
			'type'        => 'general',
			'read_at'     => null,
		] );

		Message::factory()->count( 2 )->create( [ 
			'sender_id'   => $this->guard->id,
			'receiver_id' => $this->admin->id,
			'type'        => 'violation',
			'read_at'     => null,
		] );

		$response = $this->actingAs( $this->admin )
			->getJson( '/api/dashboard/stats' );

		$response->assertStatus( 200 )
			->assertJsonStructure( [ 
				'total_students',
				'total_rooms',
				'occupied_rooms',
				'available_rooms',
				'total_payments',
				'pending_payments',
				'recent_payments',
				'unread_messages',
				'recent_messages',
				'occupancy_rate',
			] )
			->assertJson( [ 
				'total_students'   => 3,
				'total_rooms'      => 1,
				'occupied_rooms'   => 1,
				'available_rooms'  => 0,
				'total_payments'   => 2,
				'pending_payments' => 1,
				'unread_messages'  => 7,
				'occupancy_rate'   => 100.0,
			] );
	}

	public function test_student_can_view_personal_dashboard() {
		// Create payments for the student
		SemesterPayment::factory()->create( [ 
			'user_id'        => $this->student->id,
			'semester'       => '2025-fall',
			'year'           => 2025,
			'semester_type'  => 'fall',
			'amount'         => 50000,
			'payment_approved' => true,
			'payment_status'   => 'approved',
		] );

		SemesterPayment::factory()->create( [ 
			'user_id'        => $this->student->id,
			'semester'       => '2025-fall',
			'year'           => 2025,
			'semester_type'  => 'fall',
			'amount'         => 50000,
			'payment_approved' => false,
			'payment_status'   => 'pending',
		] );

		// Create messages for the student
		Message::factory()->count( 3 )->create( [ 
			'sender_id'   => $this->admin->id,
			'receiver_id' => $this->student->id,
			'type'        => 'general',
			'read_at'     => null,
		] );

		// Assign student to room
		$this->student->update( [ 'room_id' => $this->room->id ] );

		$response = $this->actingAs( $this->student )
			->getJson( '/api/dashboard/student' );

		$response->assertStatus( 200 )
			->assertJsonStructure( [ 
				'my_payments',
				'upcoming_payments',
				'my_messages',
				'room_info',
				'payment_history',
				'unread_messages_count',
			] )
			->assertJson( [ 
				'unread_messages_count' => 3,
			] );
	}

	public function test_guard_can_view_guard_dashboard() {
		// Create messages from guard
		Message::factory()->count( 2 )->create( [ 
			'sender_id'   => $this->guard->id,
			'receiver_id' => $this->admin->id,
			'type'        => 'violation',
		] );

		Message::factory()->count( 1 )->create( [ 
			'sender_id'   => $this->guard->id,
			'receiver_id' => $this->student->id,
			'type'        => 'general',
		] );

		$response = $this->actingAs( $this->guard )
			->getJson( '/api/dashboard/guard' );

		$response->assertStatus( 200 )
			->assertJsonStructure( [ 
				'total_rooms',
				'occupied_rooms',
				'my_reports',
				'recent_violations',
				'room_occupancy',
			] )
			->assertJson( [ 
				'total_rooms'    => 1,
				'occupied_rooms' => 1,
			] );
	}

	public function test_admin_can_view_monthly_stats() {
		// Create payments for different months
		SemesterPayment::factory()->create( [ 
			'user_id'        => $this->student->id,
			'semester'       => '2025-fall',
			'year'           => 2025,
			'semester_type'  => 'fall',
			'amount'         => 50000,
			'payment_approved' => true,
			'payment_status'   => 'approved',
		] );

		SemesterPayment::factory()->create( [ 
			'user_id'        => $this->student->id,
			'semester'       => '2025-spring',
			'year'           => 2025,
			'semester_type'  => 'spring',
			'amount'         => 50000,
			'payment_approved' => true,
			'payment_status'   => 'approved',
		] );

		$response = $this->actingAs( $this->admin )
			->getJson( '/api/dashboard/monthly-stats' );

		$response->assertStatus( 200 )
			->assertJsonStructure( [ 
				'current_month'   => [ 
					'total_payments',
					'total_amount',
					'new_students',
					'messages_sent',
				],
				'previous_month'  => [ 
						'total_payments',
						'total_amount',
						'new_students',
						'messages_sent',
					],
				'monthly_revenue' => [ 
						'*' => [ 
							'month',
							'year',
							'total_amount',
							'payment_count',
						]
					],
			] );
	}

	public function test_admin_can_view_payment_analytics() {
		// Create payments with different statuses and methods
		SemesterPayment::factory()->create( [ 
			'user_id'        => $this->student->id,
			'semester'       => '2025-fall',
			'year'           => 2025,
			'semester_type'  => 'fall',
			'amount'         => 50000,
			'payment_approved' => true,
			'payment_status'   => 'approved',
		] );

		SemesterPayment::factory()->create( [ 
			'user_id'        => $this->student->id,
			'semester'       => '2025-fall',
			'year'           => 2025,
			'semester_type'  => 'fall',
			'amount'         => 50000,
			'payment_approved' => false,
			'payment_status'   => 'pending',
		] );

		SemesterPayment::factory()->create( [ 
			'user_id'        => $this->student->id,
			'semester'       => '2025-fall',
			'year'           => 2025,
			'semester_type'  => 'fall',
			'amount'         => 50000,
			'payment_approved' => false,
			'payment_status'   => 'rejected',
		] );

		$response = $this->actingAs( $this->admin )
			->getJson( '/api/dashboard/payment-analytics' );

		$response->assertStatus( 200 )
			->assertJsonStructure( [ 
				'payment_methods'  => [ 
					'*' => [ 
						'method',
						'count',
						'total_amount',
					]
				],
				'payment_statuses' => [ 
						'*' => [ 
							'status',
							'count',
							'total_amount',
						]
					],
				'daily_revenue'    => [ 
						'*' => [ 
							'date',
							'total_amount',
							'payment_count',
						]
					],
			] );
	}

	public function test_student_cannot_access_admin_dashboard() {
		$response = $this->actingAs( $this->student )
			->getJson( '/api/dashboard/stats' );

		$response->assertStatus( 403 );
	}

	public function test_guard_cannot_access_admin_dashboard() {
		$response = $this->actingAs( $this->guard )
			->getJson( '/api/dashboard/stats' );

		$response->assertStatus( 403 );
	}

	public function test_admin_cannot_access_student_dashboard() {
		$response = $this->actingAs( $this->admin )
			->getJson( '/api/dashboard/student' );

		$response->assertStatus( 403 );
	}

	public function test_student_cannot_access_guard_dashboard() {
		$response = $this->actingAs( $this->student )
			->getJson( '/api/dashboard/guard' );

		$response->assertStatus( 403 );
	}

	public function test_unauthenticated_user_cannot_access_dashboard() {
		$response = $this->getJson( '/api/dashboard/stats' );
		$response->assertStatus( 401 );

		$response = $this->getJson( '/api/dashboard/student' );
		$response->assertStatus( 401 );

		$response = $this->getJson( '/api/dashboard/guard' );
		$response->assertStatus( 401 );
	}

	public function test_dashboard_with_no_data() {
		// Create a new admin without any associated data
		$newAdmin = User::factory()->create( [ 
			'role_id' => $this->admin->role_id,
			'email'   => 'newadmin@test.com',
		] );

		$response = $this->actingAs( $newAdmin )
			->getJson( '/api/dashboard/stats' );

		$response->assertStatus( 200 )
			->assertJson( [ 
				'total_students'   => 2, // Still has existing students
				'total_rooms'      => 1,
				'occupied_rooms'   => 1,
				'available_rooms'  => 0,
				'total_payments'   => 0,
				'pending_payments' => 0,
				'unread_messages'  => 0,
				'occupancy_rate'   => 100.0,
			] );
	}

	public function test_dashboard_stats_with_filters() {
		// Create payments for different date ranges
		SemesterPayment::factory()->create( [ 
			'user_id'        => $this->student->id,
			'semester'       => '2025-fall',
			'year'           => 2025,
			'semester_type'  => 'fall',
			'amount'         => 50000,
			'payment_approved' => true,
			'payment_status'   => 'approved',
		] );

		$response = $this->actingAs( $this->admin )
			->getJson( '/api/dashboard/stats?date_from=' . now()->subDays( 10 )->format( 'Y-m-d' ) );

		$response->assertStatus( 200 );

		// Should only include payments from the last 10 days
		$data = $response->json();
		$this->assertEquals( 1, $data['total_payments'] );
	}
}
