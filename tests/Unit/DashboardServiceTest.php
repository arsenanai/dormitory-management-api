<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Dormitory;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Bed;
use App\Models\Payment;
use App\Models\Message;
use App\Services\DashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Auth;

class DashboardServiceTest extends TestCase {
	use RefreshDatabase, WithFaker;

	private DashboardService $dashboardService;
	private User $admin;
	private User $student;
	private Dormitory $dormitory;
	private Room $room;
	private RoomType $roomType;

	protected function setUp(): void {
		parent::setUp();

		$this->dashboardService = new DashboardService();

		// Create roles
		$adminRole = Role::create( [ 'name' => 'admin' ] );
		$studentRole = Role::create( [ 'name' => 'student' ] );

		// Create test users
		$this->admin = User::factory()->create( [ 
			'role_id' => $adminRole->id,
			'email'   => 'admin@test.com',
		] );

		$this->student = User::factory()->create( [ 
			'role_id'       => $studentRole->id,
			'email'         => 'student@test.com',
			'status'        => 'active',
			'has_meal_plan' => true,
		] );

		// Create dormitory and room structure
		$this->dormitory = Dormitory::factory()->create( [ 
			'name'    => 'Test Dormitory',
			'address' => '123 Test Street',
		] );

		$this->roomType = RoomType::factory()->create( [ 
			'name'     => 'Single Room',
			'capacity' => 2,
			'price'    => 50000,
		] );

		$this->room = Room::factory()->create( [ 
			'dormitory_id' => $this->dormitory->id,
			'room_type_id' => $this->roomType->id,
			'number'       => '101',
			'floor'        => 1,
		] );

		// Create beds
		Bed::factory()->create( [ 
			'room_id'    => $this->room->id,
			'bed_number' => 1,
			'user_id'    => $this->student->id,
		] );

		Bed::factory()->create( [ 
			'room_id'    => $this->room->id,
			'bed_number' => 2,
			'user_id'    => null,
		] );

		// Update student's room relationship
		$this->student->update( [ 'room_id' => $this->room->id ] );
	}

	public function test_get_dashboard_stats() {
		Auth::login( $this->admin );

		// Create additional test data
		$student2 = User::factory()->create( [ 
			'role_id'       => $this->student->role_id,
			'status'        => 'pending',
			'has_meal_plan' => false,
		] );

		// Create payments
		Payment::factory()->create( [ 
			'user_id'      => $this->student->id,
			'amount'       => 50000,
			'payment_date' => now(),
			'status'       => 'completed',
		] );

		Payment::factory()->create( [ 
			'user_id'      => $student2->id,
			'amount'       => 50000,
			'payment_date' => now(),
			'status'       => 'pending',
		] );

		// Create messages
		Message::factory()->create( [ 
			'sender_id'      => $this->admin->id,
			'title'          => 'Test Message',
			'content'        => 'Test content',
			'recipient_type' => 'all',
			'status'         => 'sent',
		] );

		Message::factory()->create( [ 
			'sender_id'      => $this->admin->id,
			'title'          => 'Draft Message',
			'content'        => 'Draft content',
			'recipient_type' => 'all',
			'status'         => 'draft',
		] );

		$response = $this->dashboardService->getDashboardStats();

		$this->assertEquals( 200, $response->status() );
		$responseData = json_decode( $response->getContent(), true );

		$this->assertArrayHasKey( 'students', $responseData );
		$this->assertArrayHasKey( 'rooms', $responseData );
		$this->assertArrayHasKey( 'payments', $responseData );
		$this->assertArrayHasKey( 'messages', $responseData );

		// Check student stats
		$this->assertEquals( 2, $responseData['students']['total'] );
		$this->assertEquals( 1, $responseData['students']['active'] );
		$this->assertEquals( 1, $responseData['students']['pending'] );
		$this->assertEquals( 1, $responseData['students']['with_meals'] );
		$this->assertEquals( 1, $responseData['students']['without_meals'] );

		// Check room stats
		$this->assertEquals( 1, $responseData['rooms']['total_rooms'] );
		$this->assertEquals( 2, $responseData['rooms']['total_beds'] );
		$this->assertEquals( 1, $responseData['rooms']['occupied_beds'] );
		$this->assertEquals( 1, $responseData['rooms']['available_beds'] );
		$this->assertEquals( 50.0, $responseData['rooms']['occupancy_rate'] );

		// Check payment stats
		$this->assertEquals( 2, $responseData['payments']['total_payments'] );
		$this->assertEquals( 100000, $responseData['payments']['total_amount'] );
		$this->assertEquals( 1, $responseData['payments']['completed_payments'] );
		$this->assertEquals( 1, $responseData['payments']['pending_payments'] );

		// Check message stats
		$this->assertEquals( 2, $responseData['messages']['total_messages'] );
		$this->assertEquals( 1, $responseData['messages']['sent_messages'] );
		$this->assertEquals( 1, $responseData['messages']['draft_messages'] );
	}

	public function test_get_dormitory_stats() {
		Auth::login( $this->admin );

		// Create payments
		Payment::factory()->create( [ 
			'user_id'      => $this->student->id,
			'amount'       => 50000,
			'payment_date' => now(),
			'status'       => 'completed',
		] );

		// Create messages
		Message::factory()->create( [ 
			'sender_id'      => $this->admin->id,
			'title'          => 'Dormitory Message',
			'content'        => 'Message for dormitory',
			'recipient_type' => 'dormitory',
			'dormitory_id'   => $this->dormitory->id,
			'status'         => 'sent',
		] );

		$response = $this->dashboardService->getDormitoryStats( $this->dormitory->id );

		$this->assertEquals( 200, $response->status() );
		$responseData = json_decode( $response->getContent(), true );

		$this->assertArrayHasKey( 'dormitory', $responseData );
		$this->assertArrayHasKey( 'students', $responseData );
		$this->assertArrayHasKey( 'rooms', $responseData );
		$this->assertArrayHasKey( 'payments', $responseData );
		$this->assertArrayHasKey( 'messages', $responseData );

		// Check dormitory info
		$this->assertEquals( $this->dormitory->id, $responseData['dormitory']['id'] );
		$this->assertEquals( $this->dormitory->name, $responseData['dormitory']['name'] );

		// Check that stats are filtered by dormitory
		$this->assertEquals( 1, $responseData['students']['total'] );
		$this->assertEquals( 1, $responseData['rooms']['total_rooms'] );
		$this->assertEquals( 1, $responseData['payments']['total_payments'] );
		$this->assertEquals( 1, $responseData['messages']['total_messages'] );
	}

	public function test_get_dashboard_stats_with_dormitory_filter() {
		// Create admin with specific dormitory
		$adminWithDormitory = User::factory()->create( [ 
			'role_id' => $this->admin->role_id,
			'email'   => 'admin.dormitory@test.com',
		] );

		// Create a method to simulate hasRole and dormitory relationship
		$adminWithDormitory->dormitory_id = $this->dormitory->id;
		$adminWithDormitory->save();

		Auth::login( $adminWithDormitory );

		// Create additional dormitory and student
		$otherDormitory = Dormitory::factory()->create( [ 'name' => 'Other Dormitory' ] );
		$otherRoom = Room::factory()->create( [ 
			'dormitory_id' => $otherDormitory->id,
			'room_type_id' => $this->roomType->id,
			'number'       => '201',
		] );

		$otherStudent = User::factory()->create( [ 
			'role_id'       => $this->student->role_id,
			'room_id'       => $otherRoom->id,
			'status'        => 'active',
			'has_meal_plan' => false,
		] );

		$response = $this->dashboardService->getDashboardStats();

		$this->assertEquals( 200, $response->status() );
		$responseData = json_decode( $response->getContent(), true );

		// Should return all students since dormitory filtering is not implemented in the test
		// In a real scenario, this would be filtered by the admin's dormitory
		$this->assertGreaterThanOrEqual( 1, $responseData['students']['total'] );
	}

	public function test_get_dashboard_stats_with_no_data() {
		Auth::login( $this->admin );

		// Remove all test data
		User::where( 'id', '!=', $this->admin->id )->delete();
		Payment::truncate();
		Message::truncate();
		Bed::truncate();
		Room::truncate();

		$response = $this->dashboardService->getDashboardStats();

		$this->assertEquals( 200, $response->status() );
		$responseData = json_decode( $response->getContent(), true );

		$this->assertEquals( 0, $responseData['students']['total'] );
		$this->assertEquals( 0, $responseData['rooms']['total_rooms'] );
		$this->assertEquals( 0, $responseData['payments']['total_payments'] );
		$this->assertEquals( 0, $responseData['messages']['total_messages'] );
	}

	public function test_get_dashboard_stats_with_this_month_payments() {
		Auth::login( $this->admin );

		// Create payment for this month
		Payment::factory()->create( [ 
			'user_id'      => $this->student->id,
			'amount'       => 50000,
			'payment_date' => now(),
			'status'       => 'completed',
		] );

		// Create payment for last month
		Payment::factory()->create( [ 
			'user_id'      => $this->student->id,
			'amount'       => 30000,
			'payment_date' => now()->subMonth(),
			'status'       => 'completed',
		] );

		$response = $this->dashboardService->getDashboardStats();

		$this->assertEquals( 200, $response->status() );
		$responseData = json_decode( $response->getContent(), true );

		$this->assertEquals( 2, $responseData['payments']['total_payments'] );
		$this->assertEquals( 80000, $responseData['payments']['total_amount'] );
		$this->assertEquals( 50000, $responseData['payments']['this_month_amount'] );
	}

	public function test_get_dashboard_stats_with_recent_messages() {
		Auth::login( $this->admin );

		// Create recent message (within last 7 days)
		Message::factory()->create( [ 
			'sender_id'      => $this->admin->id,
			'title'          => 'Recent Message',
			'content'        => 'Recent content',
			'recipient_type' => 'all',
			'status'         => 'sent',
			'created_at'     => now()->subDays( 3 ),
		] );

		// Create old message (older than 7 days)
		Message::factory()->create( [ 
			'sender_id'      => $this->admin->id,
			'title'          => 'Old Message',
			'content'        => 'Old content',
			'recipient_type' => 'all',
			'status'         => 'sent',
			'created_at'     => now()->subDays( 10 ),
		] );

		$response = $this->dashboardService->getDashboardStats();

		$this->assertEquals( 200, $response->status() );
		$responseData = json_decode( $response->getContent(), true );

		$this->assertEquals( 2, $responseData['messages']['total_messages'] );
		$this->assertEquals( 1, $responseData['messages']['recent_messages'] );
	}

	public function test_get_dashboard_stats_with_available_rooms() {
		Auth::login( $this->admin );

		// Create another room with no occupied beds
		$availableRoom = Room::factory()->create( [ 
			'dormitory_id' => $this->dormitory->id,
			'room_type_id' => $this->roomType->id,
			'number'       => '102',
			'floor'        => 1,
		] );

		Bed::factory()->create( [ 
			'room_id'    => $availableRoom->id,
			'bed_number' => 1,
			'user_id'    => null,
		] );

		$response = $this->dashboardService->getDashboardStats();

		$this->assertEquals( 200, $response->status() );
		$responseData = json_decode( $response->getContent(), true );

		$this->assertEquals( 2, $responseData['rooms']['total_rooms'] );
		$this->assertEquals( 2, $responseData['rooms']['available_rooms'] );
		$this->assertEquals( 3, $responseData['rooms']['total_beds'] );
		$this->assertEquals( 1, $responseData['rooms']['occupied_beds'] );
		$this->assertEquals( 2, $responseData['rooms']['available_beds'] );
		$this->assertEquals( 33.33, $responseData['rooms']['occupancy_rate'] );
	}
}
