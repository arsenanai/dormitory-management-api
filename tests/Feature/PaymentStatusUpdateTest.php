<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\PaymentType;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PaymentStatusUpdateTest extends TestCase
{
    use RefreshDatabase;

    private User $student;
    private User $admin;
    private PaymentType $paymentType;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        // Create roles
        $studentRole = Role::factory()->create(['name' => 'student']);
        $adminRole = Role::factory()->create(['name' => 'admin']);

        // Create users
        $this->student = User::factory()->create([
            'role_id' => $studentRole->id,
        ]);

        $this->admin = User::factory()->create([
            'role_id' => $adminRole->id,
        ]);

        // Create payment type
        $this->paymentType = PaymentType::factory()->create([
            'name' => 'renting',
            'frequency' => 'semesterly',
            'calculation_method' => 'fixed',
            'fixed_amount' => 1000.00,
            'target_role' => 'student',
        ]);
    }

    #[Test]
    public function it_changes_payment_status_from_pending_to_processing_when_bank_check_is_uploaded(): void
    {
        // Create a pending payment
        $payment = Payment::factory()->create([
            'user_id' => $this->student->id,
            'payment_type_id' => $this->paymentType->id,
            'status' => PaymentStatus::Pending,
            'amount' => 1000.00,
            'payment_check' => null,
        ]);

        // Verify initial status is pending
        $this->assertEquals(PaymentStatus::Pending, $payment->status);
        $this->assertNull($payment->payment_check);

        // Upload bank check as student
        $file = UploadedFile::fake()->create('bank_check.pdf', 500, 'application/pdf');

        $response = $this->actingAs($this->student)
            ->putJson("/api/my-payments/{$payment->id}", [
                'payment_check' => $file,
            ]);

        // Assert response is successful
        $response->assertOk();
        $response->assertJsonPath('data.data.status', PaymentStatus::Processing->value);

        // Verify payment status changed to processing
        $payment->refresh();
        $this->assertEquals(PaymentStatus::Processing, $payment->status);
        $this->assertNotNull($payment->payment_check);
    }

    #[Test]
    public function it_does_not_change_status_when_payment_is_not_pending(): void
    {
        // Create a completed payment
        $payment = Payment::factory()->create([
            'user_id' => $this->student->id,
            'payment_type_id' => $this->paymentType->id,
            'status' => PaymentStatus::Completed,
            'amount' => 1000.00,
            'payment_check' => 'payment_checks/existing_check.pdf',
        ]);

        // Upload new bank check
        $file = UploadedFile::fake()->create('new_bank_check.pdf', 500, 'application/pdf');

        $response = $this->actingAs($this->student)
            ->putJson("/api/my-payments/{$payment->id}", [
                'payment_check' => $file,
            ]);

        // Assert response is successful
        $response->assertOk();

        // Verify payment status remains completed
        $payment->refresh();
        $this->assertEquals(PaymentStatus::Completed, $payment->status);
    }

    #[Test]
    public function it_does_not_change_status_when_admin_explicitly_sets_status(): void
    {
        // Create a pending payment
        $payment = Payment::factory()->create([
            'user_id' => $this->student->id,
            'payment_type_id' => $this->paymentType->id,
            'status' => PaymentStatus::Pending,
            'amount' => 1000.00,
            'payment_check' => null,
        ]);

        // Admin updates payment with explicit status
        $file = UploadedFile::fake()->create('bank_check.pdf', 500, 'application/pdf');

        $response = $this->actingAs($this->admin)
            ->putJson("/api/payments/{$payment->id}", [
                'payment_check' => $file,
                'status' => PaymentStatus::Completed->value,
            ]);

        // Assert response is successful
        $response->assertOk();

        // Verify payment status is set to completed (admin's choice)
        $payment->refresh();
        $this->assertEquals(PaymentStatus::Completed, $payment->status);
    }
}
