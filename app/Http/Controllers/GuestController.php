<?php

namespace App\Http\Controllers;

use App\Services\GuestService;
use Illuminate\Http\Request;

class GuestController extends Controller {
	public function __construct( private GuestService $guestService ) {
	}

	/**
	 * Display a listing of guests
	 */
	public function index( Request $request ) {
		$filters = $request->validate( [
			'room_id'        => 'sometimes|integer|exists:rooms,id',
			// 'payment_status' => 'sometimes|in:pending,paid,cancelled', // payment filter disabled temporarily
			'check_in_date'  => 'sometimes|date',
			'check_out_date' => 'sometimes|date',
			'search'         => 'sometimes|string|max:255',
			'per_page'       => 'sometimes|integer|min:1|max:100',
		] );

		return $this->guestService->getGuestsWithFilters( $filters );
	}

	/**
	 * Store a newly created guest
	 */
	public function store( Request $request ) {
		$validated = $request->validate( [
			'first_name'     => 'required|string|max:255',
			'last_name'      => 'required|string|max:255',
			'email'          => 'nullable|email|max:255|unique:users,email',
			'phone'          => 'nullable|string|max:20',
			'room_id'        => 'nullable|integer|exists:rooms,id',
			'check_in_date'  => 'required|date',
			'check_out_date' => 'required|date|after:check_in_date',
			// 'payment_status' => 'sometimes|in:pending,paid,cancelled', // payment disabled
			'bed_id'         => 'required|integer|exists:beds,id',
			// 'total_amount'   => 'required|numeric|min:0', // payment amount disabled for now
			'notes'          => 'nullable|string',
		] );

		// The GuestService expects a 'name' field, so we construct it.
		$validated['name'] = trim( ( $validated['first_name'] ?? '' ) . ' ' . ( $validated['last_name'] ?? '' ) );

		$guest = $this->guestService->createGuest( $validated );
		return response()->json( $guest, 201 );
	}

	/**
	 * Display the specified guest
	 */
	public function show( $id ) {
		$guest = $this->guestService->getGuestById( $id );
		return response()->json( $guest );
	}

	/**
	 * Update the specified guest
	 */
	public function update( Request $request, $id ) {
		$validated = $request->validate( [
			'first_name'     => 'sometimes|string|max:255',
			'last_name'      => 'sometimes|string|max:255',
			'name'           => 'sometimes|string|max:255',
			'email'          => 'sometimes|email|max:255',
			'phone'          => 'sometimes|string|max:20',
			'room_id'        => 'sometimes|integer|exists:rooms,id',
			'check_in_date'  => 'sometimes|date',
			'check_out_date' => 'sometimes|date|after:check_in_date',
			// 'payment_status' => 'sometimes|in:pending,paid,cancelled', // payment disabled
			'bed_id'         => 'required|integer|exists:beds,id',
			// 'total_amount'   => 'sometimes|numeric|min:0', // payment amount disabled for now
			'notes'          => 'sometimes|string',
		] );

		$guest = $this->guestService->updateGuest( $id, $validated );
		return response()->json( $guest );
	}

	/**
	 * Remove the specified guest
	 */
	public function destroy( $id ) {
		return $this->guestService->deleteGuest( $id );
	}

	/**
	 * Get available rooms for guests
	 */
	public function availableRooms() {
		$rooms = $this->guestService->getAvailableRooms();
		return response()->json( $rooms );
	}

	/**
	 * Check out a guest
	 */
	public function checkOut( $id ) {
		$guest = $this->guestService->checkOutGuest( $id );
		return response()->json( $guest );
	}

	/**
	 * Export guests to CSV
	 */
	public function export( Request $request ) {
		$filters = $request->validate( [
			'room_id'        => 'sometimes|integer|exists:rooms,id',
			// 'payment_status' => 'sometimes|in:pending,paid,cancelled', // payment filter disabled
			'check_in_date'  => 'sometimes|date',
			'check_out_date' => 'sometimes|date',
		] );

		return $this->guestService->exportGuests( $filters );
	}

	/*
	public function payments( Request $request ) {
		// Payments endpoint temporarily disabled while payment UI is removed.
		// Implementation retained here for future re-enable.
		return response()->json( [] );
	}
	*/

	public function listAll(): \Illuminate\Database\Eloquent\Collection {
		return $this->guestService->listAll();
	}
}
