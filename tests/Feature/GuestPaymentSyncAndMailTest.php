<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Listeners\ProcessMailEvent;
use App\Mail\PaymentStatusChangedMail;
use App\Mail\UserStatusChangedMail;
use App\Models\Payment;
use App\Models\PaymentType;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(ProcessMailEvent::class) ]
class GuestPaymentSyncAndMailTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $guest;
    private PaymentType $guestPaymentType;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $adminRole = Role::factory()->create([ 'name' => 'admin' ]);
        $guestRole = Role::factory()->create([ 'name' => 'guest' ]);

        $this->admin = User::factory()->create([
            'role_id' => $adminRole->id,
            'email'   => 'admin@test.local',
        ]);

        $this->guest = User::factory()->create([
            'role_id' => $guestRole->id,
            'email'   => 'guest@test.local',
            'status'  => 'active',
        ]);

        $this->guestPaymentType = PaymentType::factory()->create([
            'name'               => 'guest-fee',
            'frequency'          => 'monthly',
            'calculation_method' => 'fixed',
            'fixed_amount'       => 100.00,
            'target_role'        => 'guest',
        ]);
    }

    #[Test ]
    public function completed_to_processing_sets_guest_pending_and_queues_user_status_mail(): void
    {
        Mail::fake();

        $payment = Payment::factory()->create([
            'user_id'         => $this->guest->id,
            'payment_type_id' => $this->guestPaymentType->id,
            'status'          => PaymentStatus::Completed,
            'amount'          => 100.00,
        ]);

        $this->guest->update([ 'status' => 'active' ]);
        $this->guest->refresh();

        $response = $this->actingAs($this->admin)
            ->putJson("/api/payments/{$payment->id}", [
                'status' => PaymentStatus::Processing->value,
            ]);

        $response->assertOk();
        $this->guest->refresh();
        $this->assertSame('pending', $this->guest->status);

        Mail::assertQueued(UserStatusChangedMail::class, function ($mailable) {
            return $mailable->currentStatus === 'pending';
        });
        Mail::assertNotQueued(PaymentStatusChangedMail::class);
    }

    #[Test ]
    public function completed_to_pending_sets_guest_pending_and_queues_user_status_mail(): void
    {
        Mail::fake();

        $payment = Payment::factory()->create([
            'user_id'         => $this->guest->id,
            'payment_type_id' => $this->guestPaymentType->id,
            'status'          => PaymentStatus::Completed,
            'amount'          => 100.00,
        ]);

        $this->guest->update([ 'status' => 'active' ]);
        $this->guest->refresh();

        $response = $this->actingAs($this->admin)
            ->putJson("/api/payments/{$payment->id}", [
                'status' => PaymentStatus::Pending->value,
            ]);

        $response->assertOk();
        $this->guest->refresh();
        $this->assertSame('pending', $this->guest->status);

        Mail::assertQueued(UserStatusChangedMail::class, function ($mailable) {
            return $mailable->currentStatus === 'pending';
        });
        Mail::assertNotQueued(PaymentStatusChangedMail::class);
    }

    #[Test ]
    public function processing_to_completed_sets_guest_active_and_queues_payment_status_mail_only(): void
    {
        Mail::fake();

        $payment = Payment::factory()->create([
            'user_id'         => $this->guest->id,
            'payment_type_id' => $this->guestPaymentType->id,
            'status'          => PaymentStatus::Processing,
            'amount'          => 100.00,
        ]);

        $this->guest->update([ 'status' => 'pending' ]);
        $this->guest->refresh();

        $response = $this->actingAs($this->admin)
            ->putJson("/api/payments/{$payment->id}", [
                'status' => PaymentStatus::Completed->value,
            ]);

        $response->assertOk();
        $this->guest->refresh();
        $this->assertSame('active', $this->guest->status);

        Mail::assertQueued(PaymentStatusChangedMail::class);
        Mail::assertNotQueued(UserStatusChangedMail::class);
    }

    #[Test ]
    public function pending_to_completed_sets_guest_active_and_queues_payment_status_mail_only(): void
    {
        Mail::fake();

        $payment = Payment::factory()->create([
            'user_id'         => $this->guest->id,
            'payment_type_id' => $this->guestPaymentType->id,
            'status'          => PaymentStatus::Pending,
            'amount'          => 100.00,
        ]);

        $this->guest->update([ 'status' => 'pending' ]);
        $this->guest->refresh();

        $response = $this->actingAs($this->admin)
            ->putJson("/api/payments/{$payment->id}", [
                'status' => PaymentStatus::Completed->value,
            ]);

        $response->assertOk();
        $this->guest->refresh();
        $this->assertSame('active', $this->guest->status);

        Mail::assertQueued(PaymentStatusChangedMail::class);
        Mail::assertNotQueued(UserStatusChangedMail::class);
    }
}
