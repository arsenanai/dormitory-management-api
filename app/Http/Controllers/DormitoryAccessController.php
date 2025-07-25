<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\SemesterPayment;

class DormitoryAccessController extends Controller {
	public function check( Request $request ) {
		$user = Auth::user();
		$canAccess = false;
		if ( $user && $user->hasRole( 'student' ) ) {
			$currentSemester = SemesterPayment::getCurrentSemester();
			$payment = SemesterPayment::where( 'user_id', $user->id )
				->where( 'semester', $currentSemester )
				->where( 'payment_approved', true )
				->where( 'dormitory_access_approved', true )
				->first();
			$canAccess = (bool) $payment;
		}
		return response()->json( [ 'can_access' => $canAccess ] );
	}
}