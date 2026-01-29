<?php

namespace App\Console\Commands;

use App\Enums\PaymentStatus;
use App\Models\GuestProfile;
use App\Services\ConfigurationService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckGuestPaymentStatusCommand extends Command
{
    protected $signature = 'guests:check-payment-status';
    protected $description = 'Check guest payment status and update to pending if overdue or missing payment for current stay period';

    public function __construct(private ConfigurationService $configurationService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Checking guest payment status...');

        try {
            $updatedCount = 0;
            // Only check guests with assigned rooms and active status
            $guests = GuestProfile::with(['user', 'user.payments.type'])
                ->whereHas('user', function ($query) {
                    $query->where('status', 'active')
                          ->whereNotNull('room_id');
                })
                ->get();

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
        if (!$guest->user || $guest->user->status !== 'active') {
            return false;
        }

        if ($this->hasOverduePayments($guest)) {
            $user = $guest->user;
            $oldStatus = $user->status;
            $user->update(['status' => 'pending']);
            \Illuminate\Support\Facades\Event::dispatch(new \App\Events\MailEventOccurred('user.status_changed', [
                'user' => $user->fresh(['role']), 'old_status' => $oldStatus, 'new_status' => 'pending',
            ]));
            Log::info("Guest {$user->id} (profile {$guest->id}) status changed to pending due to overdue payments");
            return true;
        }

        if (! $this->hasPaidCurrentStay($guest)) {
            $user = $guest->user;
            $oldStatus = $user->status;
            $user->update(['status' => 'pending']);
            \Illuminate\Support\Facades\Event::dispatch(new \App\Events\MailEventOccurred('user.status_changed', [
                'user' => $user->fresh(['role']), 'old_status' => $oldStatus, 'new_status' => 'pending',
            ]));
            Log::info("Guest {$user->id} (profile {$guest->id}) status changed to pending due to missing payment for current stay period");
            return true;
        }

        return false;
    }

    private function hasOverduePayments(GuestProfile $guest): bool
    {
        $pendingPayments = $guest->user->payments()
            ->where('status', PaymentStatus::Pending)
            ->get();

        if ($pendingPayments->isEmpty()) {
            return false;
        }

        $deadlineDays = (int) ($this->configurationService->getConfiguration('payment_deadline_days') ?? 10);

        foreach ($pendingPayments as $payment) {
            if (!$payment->date_from) {
                continue;
            }

            $deadline = $payment->date_from->copy()->addDays($deadlineDays);
            if (Carbon::now()->greaterThan($deadline)) {
                return true;
            }
        }

        return false;
    }

    private function hasPaidCurrentStay(GuestProfile $guest): bool
    {
        // If guest doesn't have visit dates set, skip this check
        if (!$guest->visit_start_date || !$guest->visit_end_date) {
            return true;
        }

        $visitStart = Carbon::parse($guest->visit_start_date)->startOfDay();
        $visitEnd = Carbon::parse($guest->visit_end_date)->endOfDay();
        $today = Carbon::now()->startOfDay();

        // Only check if guest is currently within their visit period
        if (!$today->between($visitStart, $visitEnd)) {
            // Guest is not in their visit period, skip check
            return true;
        }

        // Allow grace period: check only if we're at least 1 day into the visit
        if ($today->lessThan($visitStart->copy()->addDay())) {
            // Too early in the visit, don't check yet
            return true;
        }

        // Check if guest has a completed payment that covers their visit period
        $hasPaidStay = $guest->user->payments()
            ->where('status', PaymentStatus::Completed)
            ->whereHas('type', function ($query) {
                $query->where('target_role', 'guest');
            })
            ->where(function ($query) use ($visitStart, $visitEnd, $today) {
                // Payment covers the entire visit period
                $query->where(function ($q) use ($visitStart, $visitEnd) {
                    $q->where('date_from', '<=', $visitStart)
                      ->where(function ($q2) use ($visitEnd) {
                          $q2->whereNull('date_to')
                             ->orWhere('date_to', '>=', $visitEnd);
                      });
                })
                // OR payment covers today's date within the visit period
                ->orWhere(function ($q) use ($today, $visitStart, $visitEnd) {
                    $q->where('date_from', '<=', $today)
                      ->where(function ($q2) use ($today) {
                          $q2->whereNull('date_to')
                             ->orWhere('date_to', '>=', $today);
                      })
                      // Ensure payment overlaps with visit period
                      ->where(function ($q3) use ($visitStart, $visitEnd) {
                          $q3->whereBetween('date_from', [$visitStart, $visitEnd])
                             ->orWhereBetween('date_to', [$visitStart, $visitEnd])
                             ->orWhere(function ($q4) use ($visitStart, $visitEnd) {
                                 $q4->where('date_from', '<=', $visitStart)
                                    ->where(function ($q5) use ($visitEnd) {
                                        $q5->whereNull('date_to')
                                           ->orWhere('date_to', '>=', $visitEnd);
                                    });
                             });
                      });
                });
            })
            ->exists();

        return $hasPaidStay;
    }
}
