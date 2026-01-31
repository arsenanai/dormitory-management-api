<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Http\Controllers\DashboardController;
use App\Models\Dormitory;
use App\Models\Payment;
use App\Models\PaymentType;
use App\Models\Role;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(DashboardController::class)]
class DashboardStatsTest extends TestCase
{
    use RefreshDatabase;

    private User $sudo;
    private Role $studentRole;
    private PaymentType $cateringType;
    private Dormitory $dormitory;
    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();

        $sudoRole = Role::factory()->create([ 'name' => 'sudo' ]);
        $this->sudo = User::factory()->create([ 'role_id' => $sudoRole->id ]);

        $this->studentRole = Role::factory()->create([ 'name' => 'student' ]);

        $this->cateringType = PaymentType::factory()->create([
            'name'               => 'catering',
            'frequency'          => 'monthly',
            'calculation_method' => 'fixed',
            'fixed_amount'       => 150.00,
            'target_role'        => 'student',
        ]);

        $this->dormitory = Dormitory::factory()->create();
        $roomType = RoomType::factory()->create([ 'capacity' => 2 ]);
        $this->room = Room::factory()->create([
            'dormitory_id' => $this->dormitory->id,
            'room_type_id' => $roomType->id,
        ]);
    }

    #[Test]
    public function meal_paying_students_count_includes_only_students_with_catering_paying_payment(): void
    {
        $studentWithMeal = User::factory()->create([
            'role_id'      => $this->studentRole->id,
            'room_id'      => $this->room->id,
            'dormitory_id' => $this->dormitory->id,
        ]);
        Payment::factory()->create([
            'user_id'         => $studentWithMeal->id,
            'payment_type_id' => $this->cateringType->id,
            'status'          => PaymentStatus::Pending,
            'amount'          => 150.00,
        ]);

        $studentWithoutMeal = User::factory()->create([
            'role_id'      => $this->studentRole->id,
            'room_id'      => $this->room->id,
            'dormitory_id' => $this->dormitory->id,
        ]);
        // No catering payment

        Sanctum::actingAs($this->sudo);
        $response = $this->getJson('/api/dashboard');

        $response->assertOk();
        $data = $response->json();
        $this->assertSame(2, $data['total_students']);
        $this->assertSame(1, $data['students_with_meals']);
        $this->assertSame(1, $data['students_without_meals']);
    }

    #[Test]
    public function meal_paying_includes_students_with_processing_or_completed_catering_payment(): void
    {
        $studentPending = User::factory()->create([
            'role_id'      => $this->studentRole->id,
            'room_id'      => $this->room->id,
            'dormitory_id' => $this->dormitory->id,
        ]);
        Payment::factory()->create([
            'user_id'         => $studentPending->id,
            'payment_type_id' => $this->cateringType->id,
            'status'          => PaymentStatus::Pending,
            'amount'          => 150.00,
        ]);

        $studentProcessing = User::factory()->create([
            'role_id'      => $this->studentRole->id,
            'room_id'      => $this->room->id,
            'dormitory_id' => $this->dormitory->id,
        ]);
        Payment::factory()->create([
            'user_id'         => $studentProcessing->id,
            'payment_type_id' => $this->cateringType->id,
            'status'          => PaymentStatus::Processing,
            'amount'          => 150.00,
        ]);

        $studentCompleted = User::factory()->create([
            'role_id'      => $this->studentRole->id,
            'room_id'      => $this->room->id,
            'dormitory_id' => $this->dormitory->id,
        ]);
        Payment::factory()->create([
            'user_id'         => $studentCompleted->id,
            'payment_type_id' => $this->cateringType->id,
            'status'          => PaymentStatus::Completed,
            'amount'          => 150.00,
        ]);

        Sanctum::actingAs($this->sudo);
        $response = $this->getJson('/api/dashboard');

        $response->assertOk();
        $data = $response->json();
        $this->assertSame(3, $data['total_students']);
        $this->assertSame(3, $data['students_with_meals']);
        $this->assertSame(0, $data['students_without_meals']);
    }

    #[Test]
    public function meal_paying_excludes_students_with_only_failed_or_cancelled_catering_payment(): void
    {
        $studentFailed = User::factory()->create([
            'role_id'      => $this->studentRole->id,
            'room_id'      => $this->room->id,
            'dormitory_id' => $this->dormitory->id,
        ]);
        Payment::factory()->create([
            'user_id'         => $studentFailed->id,
            'payment_type_id' => $this->cateringType->id,
            'status'          => PaymentStatus::Failed,
            'amount'          => 150.00,
        ]);

        Sanctum::actingAs($this->sudo);
        $response = $this->getJson('/api/dashboard');

        $response->assertOk();
        $data = $response->json();
        $this->assertSame(1, $data['total_students']);
        $this->assertSame(0, $data['students_with_meals']);
        $this->assertSame(1, $data['students_without_meals']);
    }

    #[Test]
    public function meal_paying_excludes_renting_only_payments(): void
    {
        $rentingType = PaymentType::factory()->create([
            'name'      => 'renting',
            'target_role' => 'student',
        ]);

        $studentRentingOnly = User::factory()->create([
            'role_id'      => $this->studentRole->id,
            'room_id'      => $this->room->id,
            'dormitory_id' => $this->dormitory->id,
        ]);
        Payment::factory()->create([
            'user_id'         => $studentRentingOnly->id,
            'payment_type_id' => $rentingType->id,
            'status'          => PaymentStatus::Pending,
            'amount'          => 5000.00,
        ]);

        Sanctum::actingAs($this->sudo);
        $response = $this->getJson('/api/dashboard');

        $response->assertOk();
        $data = $response->json();
        $this->assertSame(1, $data['total_students']);
        $this->assertSame(0, $data['students_with_meals']);
        $this->assertSame(1, $data['students_without_meals']);
    }
}
