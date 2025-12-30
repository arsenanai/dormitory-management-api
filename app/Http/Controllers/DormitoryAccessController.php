<?php

namespace App\Http\Controllers;

use App\Models\SemesterPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DormitoryAccessController extends Controller
{
    public function check(Request $request)
    {
        $user = Auth::user();
        $canAccess = false;

        if (! $user) {
            return response()->json([ 'can_access' => false ]);
        }

        // Admin and sudo users always have access
        if ($user->hasRole('admin') || $user->hasRole('sudo')) {
            $canAccess = true;
        }
        // Guest users with approved profiles have access
        elseif ($user->hasRole('guest') && $user->guestProfile && $user->guestProfile->isCurrentlyAuthorized()) {
            $canAccess = true;
        }
        // Student users need approved payments
        elseif ($user->hasRole('student')) {
            $currentSemester = SemesterPayment::getCurrentSemester();
            $payment = SemesterPayment::where('user_id', $user->id)
                ->where('semester', $currentSemester)
                ->where('payment_approved', true)
                ->where('dormitory_access_approved', true)
                ->first();
            $canAccess = (bool) $payment;
        }

        return response()->json([ 'can_access' => $canAccess ]);
    }
}
