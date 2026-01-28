<?php

namespace App\Console\Commands;

use App\Models\GuestProfile;
use App\Services\ConfigurationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckGuestPaymentStatusCommand extends Command
{
    protected $signature = 'guests:check-payment-status';
    protected $description = 'Check guest payment status and update to pending if overdue';

    public function __construct(private ConfigurationService $configurationService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Checking guest payment status...');

        try {
            $updatedCount = 0;
            $guests = GuestProfile::with(['user', 'user.payments'])->get();

            foreach ($guests as $guest) {
                if ($this->updateGuestStatus($guest)) {
                    $updatedCount++;
                }
            }

            $this->info("Updated {$updatedCount} guests to pending status");
            Log::info("Guest payment status check completed: {$updatedCount} guests updated to pending");

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Failed to check guest payment status: {$e->getMessage()}");
            Log::error("Guest payment status check failed: " . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function updateGuestStatus(GuestProfile $guest): bool
    {
        $pendingPayments = $guest->user->payments()
            ->where('status', \App\Enums\PaymentStatus::Pending)
            ->get();

        if ($pendingPayments->isEmpty()) {
            return false;
        }

        $hasOverdue = false;
        $deadlineDays = (int) ($this->configurationService->getConfiguration('payment_deadline_days') ?? 10);

        foreach ($pendingPayments as $payment) {
            $deadline = $payment->date_from->copy()->addDays($deadlineDays);
            if (\Carbon\Carbon::now()->greaterThan($deadline)) {
                $hasOverdue = true;
                break;
            }
        }

        // Only update if guest user is currently active and has overdue payments
        // Note: status is on User model, not GuestProfile
        if ($guest->user && $guest->user->status === 'active' && $hasOverdue) {
            $guest->user->update(['status' => 'pending']);

            Log::info("Guest {$guest->user->id} (profile {$guest->id}) status changed to pending due to overdue payments");
            return true;
        }

        return false;
    }
}
