<?php

namespace App\Http\Controllers;

use App\Models\Bed;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class BedController extends Controller {
	/**
	 * Update the specified bed
	 */
	public function update( Request $request, Bed $bed ): JsonResponse {
		// Check if user has permission to access this bed's room
		$user = Auth::user();
		if ( $user && $user->role && $user->role->name === 'admin' ) {
			$userDormitoryId = $user->adminProfile?->dormitory_id ?? null;
			if ( $userDormitoryId && $bed->room->dormitory_id !== $userDormitoryId ) {
				return response()->json( [ 'error' => 'Access denied: You can only modify beds in your assigned dormitory' ], 403 );
			}
		}

		// Validate request
		$validated = $request->validate( [ 
			'reserved_for_staff' => 'boolean',
			'is_occupied'        => 'boolean',
		] );

		// Update bed
		$bed->update( $validated );

		return response()->json( $bed, 200 );
	}
}
