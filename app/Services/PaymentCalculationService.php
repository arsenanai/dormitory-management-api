<?php

namespace App\Services;

use App\Models\PaymentType;
use App\Models\User;
use Carbon\Carbon;

class PaymentCalculationService
{
    public function calculateAmount(User $user, PaymentType $type): float
    {
        if (!$user->room?->roomType) {
            return 0.00;
        }

        return match ($type->calculation_method) {
            'room_semester_rate' => (float) $user->room->roomType->semester_rate,
            'room_daily_rate'    => $this->calculateDaily($user),
            'fixed'              => (float) $type->fixed_amount,
            default              => 0.00,
        };
    }

    /**
     * Calculate Guest total amount based on daily_rate and stay duration.
     */
    private function calculateDaily(User $user): float
    {
        $dailyRate = (float) $user->room->roomType->daily_rate;

        // Load guest profile if not already loaded
        $guestProfile = $user->guestProfile;

        if (!$guestProfile || !$guestProfile->visit_start_date || !$guestProfile->visit_end_date) {
            return 0.00;
        }

        $start = Carbon::parse($guestProfile->visit_start_date);
        $end = Carbon::parse($guestProfile->visit_end_date);

        // diffInDays returns the absolute difference.
        // We add +1 if you charge for both the start and end day (inclusive stay).
        $days = $start->diffInDays($end);

        // Ensure we don't multiply by 0 if stay is less than 24h but occupies a date
        $days = $days > 0 ? $days : 1;

        return $dailyRate * $days;
    }
}
