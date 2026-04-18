<?php

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Models\GuestProfile;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DormitoryAccessController extends Controller
{
    public function check(Request $request): JsonResponse
    {
        $user = Auth::user();
        $canAccess = false;

        if (! $user) {
            return response()->json([ 'can_access' => false ]);
        }

        if ($user->hasRole('admin') || $user->hasRole('sudo')) {
            $canAccess = true;
        } elseif ($user->hasRole('guest')) {
            /** @var GuestProfile|null $guestProfile */
            $guestProfile = $user->guestProfile;
            if ($guestProfile && $this->isGuestAuthorized($guestProfile)) {
                $canAccess = true;
            }
        } elseif ($user->hasRole('student')) {
            $payment = $this->getLatestActivePaymentForUser($user);
            $canAccess = $payment !== null;
        }

        return response()->json([ 'can_access' => $canAccess ]);
    }

    private function isGuestAuthorized(GuestProfile $profile): bool
    {
        return $profile->is_approved === true;
    }

    private function getLatestActivePaymentForUser(\App\Models\User $user): ?Payment
    {
        return Payment::where('user_id', $user->id)
            ->where('status', PaymentStatus::Completed)
            ->whereDate('date_to', '>=', now()->toDateString())
            ->orderBy('date_to', 'desc')
            ->first();
    }
}
