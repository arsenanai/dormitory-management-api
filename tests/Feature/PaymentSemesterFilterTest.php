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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * @coversDefaultClass \App\Http\Controllers\PaymentController
 */
class PaymentSemesterFilterTest extends TestCase
{
    use RefreshDatabase;

    private Role $studentRole;
    private Role $adminRole;
    private Dormitory $dormitory;
    private RoomType $roomType;
    private Room $room;
    private Bed $bed;
    private User $student;
    private PaymentType $paymentType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->studentRole = Role::factory()->create([ 'name' => 'student' ]);
        $this->adminRole = Role::factory()->create([ 'name' => 'admin' ]);

        $this->dormitory = Dormitory::factory()->create([
            'gender' => 'male',
        ]);

        $this->roomType = RoomType::factory()->create([
            'name'     => 'Standard',
            'capacity' => 2,
        ]);

        $this->room = Room::factory()->create([
            'dormitory_id'  => $this->dormitory->id,
            'room_type_id'  => $this->roomType->id,
            'number'        => '101',
            'occupant_type' => 'student',
        ]);

        $this->bed = $this->room->beds()->first();

        $this->student = User::factory()->create([
            'role_id'      => $this->studentRole->id,
            'dormitory_id' => $this->dormitory->id,
            'room_id'      => $this->room->id,
        ]);

        \App\Models\StudentProfile::factory()->create([
            'user_id' => $this->student->id,
        ]);

        $this->paymentType = PaymentType::factory()->create([
            'name'        => 'renting',
            'frequency'   => 'semesterly',
            'target_role' => 'student',
        ]);
    }

    /** @covers \App\Http\Controllers\PaymentController::myPayments */
    public function test_student_can_filter_payments_by_semester_date_range(): void
    {
        // Create payments for different semesters
        // Fall 2024: September 1 - December 31, 2024
        $fallPayment = Payment::factory()->create([
            'user_id'         => $this->student->id,
            'payment_type_id' => $this->paymentType->id,
            'date_from'       => '2024-09-01',
            'date_to'         => '2024-12-31',
            'status'          => PaymentStatus::Completed,
            'amount'          => 5000.00,
        ]);

        // Spring 2024: January 1 - May 31, 2024
        $springPayment = Payment::factory()->create([
            'user_id'         => $this->student->id,
            'payment_type_id' => $this->paymentType->id,
            'date_from'       => '2024-01-01',
            'date_to'         => '2024-05-31',
            'status'          => PaymentStatus::Completed,
            'amount'          => 5000.00,
        ]);

        // Fall 2023: September 1 - December 31, 2023
        $fall2023Payment = Payment::factory()->create([
            'user_id'         => $this->student->id,
            'payment_type_id' => $this->paymentType->id,
            'date_from'       => '2023-09-01',
            'date_to'         => '2023-12-31',
            'status'          => PaymentStatus::Completed,
            'amount'          => 5000.00,
        ]);

        // Filter by Fall 2024 semester (date_from: 2024-09-01, date_to: 2024-12-31)
        $response = $this->actingAs($this->student)->getJson('/api/my-payments?date_from=2024-09-01&date_to=2024-12-31');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'amount',
                    'status',
                    'dateFrom',
                    'dateTo',
                ],
            ],
        ]);

        $payments = collect($response->json('data'));

        // Should only return Fall 2024 payment
        $this->assertCount(1, $payments);
        $this->assertEquals($fallPayment->id, $payments->first()['id']);
    }

    /** @covers \App\Http\Controllers\PaymentController::myPayments */
    public function test_student_semester_filter_returns_payments_within_date_range(): void
    {
        // Create payments that overlap with semester dates
        // Spring 2024: January 1 - May 31, 2024

        // Payment that starts before semester but ends during semester
        $overlappingStart = Payment::factory()->create([
            'user_id'         => $this->student->id,
            'payment_type_id' => $this->paymentType->id,
            'date_from'       => '2023-12-01',
            'date_to'         => '2024-02-28',
            'status'          => PaymentStatus::Completed,
            'amount'          => 5000.00,
        ]);

        // Payment that starts during semester and ends after
        $overlappingEnd = Payment::factory()->create([
            'user_id'         => $this->student->id,
            'payment_type_id' => $this->paymentType->id,
            'date_from'       => '2024-04-01',
            'date_to'         => '2024-06-30',
            'status'          => PaymentStatus::Completed,
            'amount'          => 5000.00,
        ]);

        // Payment completely within semester
        $withinSemester = Payment::factory()->create([
            'user_id'         => $this->student->id,
            'payment_type_id' => $this->paymentType->id,
            'date_from'       => '2024-02-01',
            'date_to'         => '2024-04-30',
            'status'          => PaymentStatus::Completed,
            'amount'          => 5000.00,
        ]);

        // Payment completely outside semester
        $outsideSemester = Payment::factory()->create([
            'user_id'         => $this->student->id,
            'payment_type_id' => $this->paymentType->id,
            'date_from'       => '2024-06-01',
            'date_to'         => '2024-08-31',
            'status'          => PaymentStatus::Completed,
            'amount'          => 5000.00,
        ]);

        // Filter by Spring 2024 semester (date_from: 2024-01-01, date_to: 2024-05-31)
        $response = $this->actingAs($this->student)->getJson('/api/my-payments?date_from=2024-01-01&date_to=2024-05-31');

        $response->assertStatus(200);
        $payments = collect($response->json('data'));

        // Should return payments that overlap with the semester range
        $paymentIds = $payments->pluck('id')->toArray();

        $this->assertContains($overlappingStart->id, $paymentIds);
        $this->assertContains($overlappingEnd->id, $paymentIds);
        $this->assertContains($withinSemester->id, $paymentIds);
        $this->assertNotContains($outsideSemester->id, $paymentIds);
    }

    /** @covers \App\Http\Controllers\PaymentController::myPayments */
    public function test_student_semester_filter_works_with_empty_date_range(): void
    {
        Payment::factory()->count(3)->create([
            'user_id'         => $this->student->id,
            'payment_type_id' => $this->paymentType->id,
            'status'          => PaymentStatus::Completed,
        ]);

        // Request without date filters should return all payments
        $response = $this->actingAs($this->student)->getJson('/api/my-payments');

        $response->assertStatus(200);
        $payments = collect($response->json('data'));

        $this->assertGreaterThanOrEqual(3, $payments->count());
    }

    /** @covers \App\Http\Controllers\PaymentController::myPayments */
    public function test_student_semester_filter_works_with_partial_date_range(): void
    {
        // Create payments
        $paymentBefore = Payment::factory()->create([
            'user_id'         => $this->student->id,
            'payment_type_id' => $this->paymentType->id,
            'date_from'       => '2024-01-01',
            'date_to'         => '2024-03-31',
            'status'          => PaymentStatus::Completed,
        ]);

        $paymentAfter = Payment::factory()->create([
            'user_id'         => $this->student->id,
            'payment_type_id' => $this->paymentType->id,
            'date_from'       => '2024-06-01',
            'date_to'         => '2024-08-31',
            'status'          => PaymentStatus::Completed,
        ]);

        // Filter with only date_from (should return payments that end on or after this date)
        $response = $this->actingAs($this->student)->getJson('/api/my-payments?date_from=2024-04-01');

        $response->assertStatus(200);
        $payments = collect($response->json('data'));
        $paymentIds = $payments->pluck('id')->toArray();

        $this->assertNotContains($paymentBefore->id, $paymentIds);
        $this->assertContains($paymentAfter->id, $paymentIds);
    }

    /** @covers \App\Http\Controllers\PaymentController::myPayments */
    public function test_student_semester_filter_validates_date_format(): void
    {
        $response = $this->actingAs($this->student)->getJson('/api/my-payments?date_from=invalid-date&date_to=2024-12-31');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors([ 'date_from' ]);
    }
}
