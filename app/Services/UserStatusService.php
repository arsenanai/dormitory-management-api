<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Events\MailEventOccurred;
use App\Models\Payment;
use App\Models\User;

class UserStatusService
{
    /**
     * Sync user status based on payment statuses
     */
    public function syncUserStatus(int $userId): void
    {
        /** @var User $user */
        $user = User::findOrFail($userId);
        $oldStatus = $user->status;

        // Get user's active payments (eager-load type for semester_rent check)
        /** @var \Illuminate\Database\Eloquent\Collection<int, Payment> $payments */
        $payments = $user->payments()->where('status', PaymentStatus::Pending)->orWhere('status', PaymentStatus::PartiallyPaid)->orWhere('status', PaymentStatus::Completed)->with('type')->get();

        if ($payments->isEmpty()) {
            return; // No active payments to evaluate
        }

        // Check if user is student or guest for different logic
        if ($user->hasRole('student')) {
            // Students: active if semester rent payment is completed
            /** @var Payment|null $semesterPayment */
            $semesterPayment = $payments->firstWhere('type.name', 'semester_rent');
            if ($semesterPayment && $semesterPayment->status === PaymentStatus::Completed) {
                $user->status = 'active';
            } else {
                $user->status = 'pending';
            }
        } elseif ($user->hasRole('guest')) {
            // Guests: active if ALL payments are completed
            $allCompleted = $payments->every(fn (Payment $payment, int $key) => $payment->status === PaymentStatus::Completed);
            $user->status = $allCompleted ? 'active' : 'pending';
        }

        if ($oldStatus !== $user->status) {
            $user->save();

            // Fire event for user status change
            event(new MailEventOccurred('user_status_changed', [
                'user' => $user,
                'old_status' => $oldStatus,
                'new_status' => $user->status,
            ]));
        }
    }
}
