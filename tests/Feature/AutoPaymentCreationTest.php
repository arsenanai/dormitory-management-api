<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Models\Bed;
use App\Models\Dormitory;
use App\Models\Payment;
use App\Models\PaymentType;
use App\Models\Role;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use App\Services\UserAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutoPaymentCreationTest extends TestCase
{
    use RefreshDatabase;

    private Role $studentRole;
    private Role $guestRole;
    private RoomType $roomType;
    private Room $room;
    private Bed $bed;
    private Dormitory $dormitory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->studentRole = Role::factory()->create(['name' => 'student']);
        $this->guestRole = Role::factory()->create(['name' => 'guest']);

        $this->dormitory = Dormitory::factory()->create([
            'name' => 'Test Dormitory',
            'gender' => 'male',
        ]);

        $this->roomType = RoomType::factory()->create([
            'name' => 'Standard',
            'daily_rate' => 50.00,
            'semester_rate' => 5000.00,
            'capacity' => 2, // RoomFactory will create 2 beds automatically
        ]);

        $this->room = Room::factory()->create([
            'dormitory_id' => $this->dormitory->id,
            'room_type_id' => $this->roomType->id,
            'number' => '101',
        ]);

        // Use the first bed created by RoomFactory
        $this->bed = $this->room->beds()->first();
    }

    public function test_student_registration_creates_pending_payments(): void
    {
        // Create PaymentType for student registration
        $rentingType = PaymentType::factory()->create([
            'name' => 'renting',
            'frequency' => 'semesterly',
            'calculation_method' => 'room_semester_rate',
            'target_role' => 'student',
            'trigger_event' => 'registration',
        ]);

        $cateringType = PaymentType::factory()->create([
            'name' => 'catering',
            'frequency' => 'monthly',
            'calculation_method' => 'fixed',
            'fixed_amount' => 150.00,
            'target_role' => 'student',
            'trigger_event' => 'registration',
        ]);

        $authService = app(UserAuthService::class);

        // Register a student with room assignment
        $student = $authService->registerStudent([
            'name' => 'John Doe',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'phone_numbers' => ['+77001234567'],
            'room_id' => $this->room->id,
            'faculty' => 'Engineering',
            'specialist' => 'Computer Science',
            'enrollment_year' => 2024,
            'gender' => 'male',
            'deal_number' => 'DEAL-001',
            'country' => 'Kazakhstan',
            'region' => 'Almaty',
            'city' => 'Almaty',
            'iin' => '123456789012',
            'student_id' => 'STU-001',
            'agree_to_dormitory_rules' => true,
        ]);

        // Assert payments were created
        $payments = Payment::where('user_id', $student->id)->get();
        $this->assertCount(2, $payments);

        // Check renting payment
        $rentingPayment = $payments->firstWhere('payment_type_id', $rentingType->id);
        $this->assertNotNull($rentingPayment);
        $this->assertEquals(5000.00, (float) $rentingPayment->amount);
        $this->assertEquals(PaymentStatus::Pending, $rentingPayment->status);
        $this->assertEquals($rentingType->id, $rentingPayment->payment_type_id);

        // Check catering payment
        $cateringPayment = $payments->firstWhere('payment_type_id', $cateringType->id);
        $this->assertNotNull($cateringPayment);
        $this->assertEquals(150.00, (float) $cateringPayment->amount);
        $this->assertEquals(PaymentStatus::Pending, $cateringPayment->status);
    }

    public function test_guest_registration_creates_pending_payments(): void
    {
        // Create PaymentType for guest registration
        $guestStayType = PaymentType::factory()->create([
            'name' => 'guest_stay',
            'frequency' => 'once',
            'calculation_method' => 'room_daily_rate',
            'target_role' => 'guest',
            'trigger_event' => 'registration',
        ]);

        $authService = app(UserAuthService::class);

        // Register a guest with room and dates
        $guest = $authService->registerGuest([
            'name' => 'Jane Guest',
            'first_name' => 'Jane',
            'last_name' => 'Guest',
            'email' => 'jane@example.com',
            'password' => 'password123',
            'phone_numbers' => ['+77001234568'],
            'room_id' => $this->room->id,
            'visit_start_date' => '2026-01-01',
            'visit_end_date' => '2026-01-11', // 10 days
            'purpose_of_visit' => 'Conference',
            'host_name' => 'John Host',
            'host_contact' => '+77001234569',
        ]);

        // Reload guest with profile
        $guest->load('guestProfile');

        // Assert payment was created
        $payments = Payment::where('user_id', $guest->id)->get();
        $this->assertCount(1, $payments);

        $payment = $payments->first();
        $this->assertEquals($guestStayType->id, $payment->payment_type_id);
        $this->assertEquals(500.00, (float) $payment->amount); // 10 days * 50.00
        $this->assertEquals(PaymentStatus::Pending, $payment->status);
    }

    public function test_guest_new_booking_creates_pending_payments(): void
    {
        // Create admin user for authentication with dormitory
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $admin = User::factory()->create([
            'role_id' => $adminRole->id,
            'dormitory_id' => $this->dormitory->id,
        ]);
        $this->dormitory->update(['admin_id' => $admin->id]);
        $this->actingAs($admin);

        // Create guest user
        $guest = User::factory()->create([
            'role_id' => $this->guestRole->id,
            'room_id' => $this->room->id,
        ]);

        \App\Models\GuestProfile::factory()->create([
            'user_id' => $guest->id,
            'visit_start_date' => '2026-01-01',
            'visit_end_date' => '2026-01-05',
        ]);

        // Create PaymentType for new booking
        $bookingType = PaymentType::factory()->create([
            'name' => 'guest_booking',
            'frequency' => 'once',
            'calculation_method' => 'room_daily_rate',
            'target_role' => 'guest',
            'trigger_event' => 'new_booking',
        ]);

        $guestService = app(\App\Services\GuestService::class);

        // Update guest with new booking dates
        $guestService->updateGuest($guest->id, [
            'check_in_date' => '2026-02-01',
            'check_out_date' => '2026-02-15', // 14 days
        ]);

        // Assert payment was created
        $payments = Payment::where('user_id', $guest->id)
            ->where('payment_type_id', $bookingType->id)
            ->get();

        $this->assertGreaterThanOrEqual(1, $payments->count());
        $payment = $payments->first();
        $this->assertEquals(700.00, (float) $payment->amount); // 14 days * 50.00
        $this->assertEquals(PaymentStatus::Pending, $payment->status);
    }

    public function test_room_type_change_creates_pending_payments(): void
    {
        // Create admin user for authentication
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $admin = User::factory()->create(['role_id' => $adminRole->id]);
        $this->actingAs($admin);

        // Create student with room
        $student = User::factory()->create([
            'role_id' => $this->studentRole->id,
            'room_id' => $this->room->id,
        ]);

        \App\Models\StudentProfile::factory()->create([
            'user_id' => $student->id,
        ]);

        // Create new room type (lux) with capacity 2
        $luxRoomType = RoomType::factory()->create([
            'name' => 'Lux',
            'daily_rate' => 100.00,
            'semester_rate' => 10000.00,
            'capacity' => 2,
        ]);

        $luxRoom = Room::factory()->create([
            'dormitory_id' => $this->dormitory->id,
            'room_type_id' => $luxRoomType->id,
            'number' => '201',
        ]);

        // Use the first bed created by RoomFactory (capacity 2 creates beds 1 and 2)
        $luxBed = $luxRoom->beds()->first();

        // Create PaymentType for room type change
        $roomChangeType = PaymentType::factory()->create([
            'name' => 'room_upgrade',
            'frequency' => 'semesterly',
            'calculation_method' => 'room_semester_rate',
            'target_role' => 'student',
            'trigger_event' => 'room_type_change',
        ]);

        $studentService = app(\App\Services\StudentService::class);

        // Update student with new room (different room type)
        $studentService->updateStudent($student->id, [
            'bed_id' => $luxBed->id,
        ], $admin);

        // Assert payment was created
        $payments = Payment::where('user_id', $student->id)
            ->where('payment_type_id', $roomChangeType->id)
            ->get();

        $this->assertGreaterThanOrEqual(1, $payments->count());
        $payment = $payments->first();
        $this->assertEquals(10000.00, (float) $payment->amount);
        $this->assertEquals(PaymentStatus::Pending, $payment->status);
    }

    public function test_no_duplicate_payments_created_on_multiple_registration_calls(): void
    {
        // Create PaymentType
        $rentingType = PaymentType::factory()->create([
            'name' => 'renting',
            'frequency' => 'semesterly',
            'calculation_method' => 'room_semester_rate',
            'target_role' => 'student',
            'trigger_event' => 'registration',
        ]);

        $authService = app(UserAuthService::class);

        // Register student
        $student = $authService->registerStudent([
            'name' => 'John Doe',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'phone_numbers' => ['+77001234567'],
            'room_id' => $this->room->id,
            'faculty' => 'Engineering',
            'specialist' => 'Computer Science',
            'enrollment_year' => 2024,
            'gender' => 'male',
            'deal_number' => 'DEAL-001',
            'country' => 'Kazakhstan',
            'region' => 'Almaty',
            'city' => 'Almaty',
            'iin' => '123456789012',
            'student_id' => 'STU-002',
            'agree_to_dormitory_rules' => true,
        ]);

        $initialCount = Payment::where('user_id', $student->id)->count();

        // Try to create payments again (should not create duplicates)
        $student->load(['role', 'room.roomType']);
        $student->createPaymentsForTriggerEvent('registration');

        $finalCount = Payment::where('user_id', $student->id)->count();

        // Should have same count (no duplicates)
        $this->assertEquals($initialCount, $finalCount);
    }

    public function test_payment_not_created_when_user_has_no_room(): void
    {
        // Create PaymentType
        PaymentType::factory()->create([
            'name' => 'renting',
            'frequency' => 'semesterly',
            'calculation_method' => 'room_semester_rate',
            'target_role' => 'student',
            'trigger_event' => 'registration',
        ]);

        $authService = app(UserAuthService::class);

        // Register student WITHOUT room assignment
        $student = $authService->registerStudent([
            'name' => 'John Doe',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'phone_numbers' => ['+77001234567'],
            'faculty' => 'Engineering',
            'specialist' => 'Computer Science',
            'enrollment_year' => 2024,
            'gender' => 'male',
            'deal_number' => 'DEAL-001',
            'country' => 'Kazakhstan',
            'region' => 'Almaty',
            'city' => 'Almaty',
            'iin' => '123456789012',
            'student_id' => 'STU-003',
            'agree_to_dormitory_rules' => true,
        ]);

        // Assert no payments were created
        $payments = Payment::where('user_id', $student->id)->get();
        $this->assertCount(0, $payments);
    }
}
