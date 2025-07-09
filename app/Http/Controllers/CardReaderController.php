<?php

namespace App\Http\Controllers;

use App\Services\CardReaderService;
use Illuminate\Http\Request;

class CardReaderController extends Controller {
	public function __construct( private CardReaderService $cardReaderService ) {
	}

	/**
	 * Log a card reader entry/exit
	 */
	public function logEntry( Request $request ) {
		$validated = $request->validate( [ 
			'card_number' => 'required|string|max:50',
			'location'    => 'required|string|max:100',
			'timestamp'   => 'sometimes|date',
		] );

		return $this->cardReaderService->logCardReaderEntry( $validated );
	}

	/**
	 * Get user presence status
	 */
	public function getUserPresence( $userId ) {
		return $this->cardReaderService->getUserPresenceStatus( $userId );
	}

	/**
	 * Get users currently inside
	 */
	public function getUsersInside( Request $request ) {
		$validated = $request->validate( [ 
			'location' => 'sometimes|string|max:100',
		] );

		$users = $this->cardReaderService->getUsersCurrentlyInside( $validated['location'] ?? null );
		return response()->json( $users );
	}

	/**
	 * Get card reader logs
	 */
	public function getLogs( Request $request ) {
		$filters = $request->validate( [ 
			'user_id'   => 'sometimes|integer|exists:users,id',
			'location'  => 'sometimes|string|max:100',
			'action'    => 'sometimes|in:entry,exit',
			'date_from' => 'sometimes|date',
			'date_to'   => 'sometimes|date',
			'per_page'  => 'sometimes|integer|min:1|max:100',
		] );

		return $this->cardReaderService->getCardReaderLogs( $filters );
	}

	/**
	 * Get daily attendance report
	 */
	public function getDailyReport( Request $request ) {
		$validated = $request->validate( [ 
			'date' => 'sometimes|date',
		] );

		$report = $this->cardReaderService->getDailyAttendanceReport( $validated['date'] ?? null );
		return response()->json( $report );
	}

	/**
	 * Get monthly attendance statistics
	 */
	public function getMonthlyStats( Request $request ) {
		$validated = $request->validate( [ 
			'month' => 'sometimes|integer|min:1|max:12',
			'year'  => 'sometimes|integer|min:2020|max:2030',
		] );

		$stats = $this->cardReaderService->getMonthlyAttendanceStats(
			$validated['month'] ?? null,
			$validated['year'] ?? null
		);

		return response()->json( $stats );
	}

	/**
	 * Export attendance report
	 */
	public function exportReport( Request $request ) {
		$filters = $request->validate( [ 
			'date_from' => 'sometimes|date',
			'date_to'   => 'sometimes|date',
			'location'  => 'sometimes|string|max:100',
		] );

		return $this->cardReaderService->exportAttendanceReport( $filters );
	}

	/**
	 * Sync card reader
	 */
	public function syncCardReader() {
		$result = $this->cardReaderService->syncCardReader();
		return response()->json( $result );
	}
}
