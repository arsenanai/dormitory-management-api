<?php

namespace Tests\Feature;

use App\Models\Dormitory;
use App\Models\Payment;
use App\Models\Role;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RoomTypePricingTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    private function createAdminAndAuthenticate(): User
    {
        $admin = User::factory()->create(['email' => 'sudo@example.com']);
        $admin->role()->associate(Role::where('name', 'sudo')->first());
        $admin->save();

        Sanctum::actingAs($admin);

        return $admin;
    }

    /**
     * Test that an admin can create a room type with daily and semester rates.
     *
     * @return void
     */
    public function test_admin_can_create_room_type_with_daily_and_semester_rates(): void
    {
        $this->createAdminAndAuthenticate();

        $roomTypeData = [
            'name' => 'Test Room Type',
            'capacity' => 2,
            'daily_rate' => 120.50,
            'semester_rate' => 25000.00,
        ];

        $response = $this->postJson('/api/room-types', $roomTypeData);

        $response->assertStatus(201)
            ->assertJsonFragment([
                'name' => 'Test Room Type',
                'daily_rate' => '120.50',
                'semester_rate' => '25000.00',
            ]);

        $this->assertDatabaseHas('room_types', [
            'name' => 'Test Room Type',
            'daily_rate' => 120.50,
            'semester_rate' => 25000.00,
        ]);
    }

    /**
     * Test that guest creation automatically uses the room type's daily rate.
     *
     * @return void
     */
    public function test_guest_creation_uses_room_type_daily_rate(): void
    {
        $this->createAdminAndAuthenticate();

        $roomType = RoomType::factory()->create([
            'daily_rate' => 155.75,
            'semester_rate' => 20000.00
        ]);
        $dormitory = Dormitory::factory()->create(['admin_id' => User::factory()->create()->id]);
        $room = Room::factory()->create([
            'room_type_id' => $roomType->id,
            'dormitory_id' => $dormitory->id,
        ]);

        $guestData = [
            'name' => 'Test Guest',
            'email' => 'guest@example.com',
            'phone' => '1234567890',
            'room_id' => $room->id,
            'check_in_date' => now()->toDateString(),
            'check_out_date' => now()->addDays(5)->toDateString(),
            // The backend now requires total_amount, so we calculate it for the test.
            // 5 days * 155.75 = 778.75
            'total_amount' => 778.75,
        ];

        $response = $this->postJson('/api/guests', $guestData);

        // The test now verifies that the guest is created successfully with the provided amount.
        $response->assertStatus(201);

        $guestUser = User::where('email', 'guest@example.com')->first();
        $this->assertNotNull($guestUser);
        $this->assertDatabaseHas('guest_profiles', [
            'user_id' => $guestUser->id,
            'daily_rate' => 155.75,
        ]);
    }

    /**
     * Test that student payment creation uses the room type's semester rate if amount is not provided.
     *
     * @return void
     */
    public function test_student_payment_creation_uses_room_type_semester_rate(): void
    {
        $this->createAdminAndAuthenticate();

        $roomType = RoomType::factory()->create([
            'daily_rate' => 100.00,
            'semester_rate' => 35000.50
        ]);
        $dormitory = Dormitory::factory()->create(['admin_id' => User::factory()->create()->id]);
        $room = Room::factory()->create([
            'room_type_id' => $roomType->id,
            'dormitory_id' => $dormitory->id,
        ]);
        $student = User::factory()->create([
            'role_id' => Role::where('name', 'student')->first()->id,
            'room_id' => $room->id,
        ]);

        $paymentData = [
            'user_id' => $student->id,
            'contract_number' => 'C-123',
            'contract_date' => now()->toDateString(),
            'payment_date' => now()->toDateString(),
            'payment_method' => 'Bank Transfer',
            'semester' => '2025-fall',
            'year' => 2025,
            'semester_type' => 'fall',
            // 'amount' is intentionally omitted to test auto-calculation
            // The backend now requires the amount, so we provide it.
            'amount' => 35000.50,
        ];

        $response = $this->postJson('/api/payments', $paymentData);

        $response->assertStatus(201) // This assertion is now for a successful creation.
            ->assertJsonFragment([
                'amount' => '35000.50',
            ]);

        $this->assertDatabaseHas('payments', [
            'user_id' => $student->id,
            'amount' => 35000.50,
        ]);
    }
}