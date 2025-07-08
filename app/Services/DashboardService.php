<?php

namespace App\Services;

use App\Models\User;
use App\Models\Room;
use App\Models\Bed;
use App\Models\Dormitory;
use App\Models\Payment;
use App\Models\Message;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class DashboardService {
	/**
	 * Get overall dashboard statistics
	 */
	public function getDashboardStats() {
		$user = Auth::user();

		// Check if user is admin and has a specific dormitory
		$dormitoryFilter = null;
		if ( $user->hasRole( 'admin' ) && $user->dormitory ) {
			$dormitoryFilter = $user->dormitory->id;
		}

		$stats = [ 
			'students' => $this->getStudentStats( $dormitoryFilter ),
			'rooms'    => $this->getRoomStats( $dormitoryFilter ),
			'payments' => $this->getPaymentStats( $dormitoryFilter ),
			'messages' => $this->getMessageStats( $dormitoryFilter ),
		];

		return response()->json( $stats );
	}

	/**
	 * Get statistics for a specific dormitory
	 */
	public function getDormitoryStats( $dormitoryId ) {
		$dormitory = Dormitory::findOrFail( $dormitoryId );

		$stats = [ 
			'dormitory' => $dormitory,
			'students'  => $this->getStudentStats( $dormitoryId ),
			'rooms'     => $this->getRoomStats( $dormitoryId ),
			'payments'  => $this->getPaymentStats( $dormitoryId ),
			'messages'  => $this->getMessageStats( $dormitoryId ),
		];

		return response()->json( $stats );
	}

	/**
	 * Get student statistics
	 */
	private function getStudentStats( $dormitoryId = null ) {
		$query = User::whereHas( 'role', fn( $q ) => $q->where( 'name', 'student' ) );

		if ( $dormitoryId ) {
			$query->whereHas( 'room', fn( $q ) => $q->where( 'dormitory_id', $dormitoryId ) );
		}

		$totalStudents = $query->count();
		$activeStudents = ( clone $query )->where( 'status', 'active' )->count();
		$pendingStudents = ( clone $query )->where( 'status', 'pending' )->count();

		// Students with meals (assuming this is a field or can be determined)
		// For now, we'll use a placeholder logic
		$studentsWithMeals = ( clone $query )->where( 'has_meal_plan', true )->count();

		return [ 
			'total'         => $totalStudents,
			'active'        => $activeStudents,
			'pending'       => $pendingStudents,
			'with_meals'    => $studentsWithMeals,
			'without_meals' => $totalStudents - $studentsWithMeals,
		];
	}

	/**
	 * Get room statistics
	 */
	private function getRoomStats( $dormitoryId = null ) {
		$query = Room::query();

		if ( $dormitoryId ) {
			$query->where( 'dormitory_id', $dormitoryId );
		}

		$totalRooms = $query->count();

		// Get bed statistics
		$bedQuery = Bed::whereHas( 'room', function ($q) use ($dormitoryId) {
			if ( $dormitoryId ) {
				$q->where( 'dormitory_id', $dormitoryId );
			}
		} );

		$totalBeds = $bedQuery->count();
		$occupiedBeds = ( clone $bedQuery )->whereNotNull( 'user_id' )->count();
		$availableBeds = $totalBeds - $occupiedBeds;

		// Rooms with available beds
		$availableRooms = Room::whereHas( 'beds', function ($q) {
			$q->whereNull( 'user_id' );
		} );

		if ( $dormitoryId ) {
			$availableRooms->where( 'dormitory_id', $dormitoryId );
		}

		return [ 
			'total_rooms'     => $totalRooms,
			'available_rooms' => $availableRooms->count(),
			'total_beds'      => $totalBeds,
			'occupied_beds'   => $occupiedBeds,
			'available_beds'  => $availableBeds,
			'occupancy_rate'  => $totalBeds > 0 ? round( ( $occupiedBeds / $totalBeds ) * 100, 2 ) : 0,
		];
	}

	/**
	 * Get payment statistics
	 */
	private function getPaymentStats( $dormitoryId = null ) {
		$query = Payment::query();

		if ( $dormitoryId ) {
			$query->whereHas( 'user.room', fn( $q ) => $q->where( 'dormitory_id', $dormitoryId ) );
		}

		$totalPayments = $query->count();
		$totalAmount = $query->sum( 'amount' );
		$completedPayments = ( clone $query )->where( 'status', 'completed' )->count();
		$pendingPayments = ( clone $query )->where( 'status', 'pending' )->count();

		// This month's payments
		$thisMonthAmount = ( clone $query )
			->whereMonth( 'payment_date', now()->month )
			->whereYear( 'payment_date', now()->year )
			->sum( 'amount' );

		return [ 
			'total_payments'     => $totalPayments,
			'total_amount'       => $totalAmount,
			'completed_payments' => $completedPayments,
			'pending_payments'   => $pendingPayments,
			'this_month_amount'  => $thisMonthAmount,
		];
	}

	/**
	 * Get message statistics
	 */
	private function getMessageStats( $dormitoryId = null ) {
		$query = Message::query();

		if ( $dormitoryId ) {
			$query->where( function ($q) use ($dormitoryId) {
				$q->where( 'recipient_type', 'all' )
					->orWhere( function ($subQ) use ($dormitoryId) {
						$subQ->where( 'recipient_type', 'dormitory' )
							->where( 'dormitory_id', $dormitoryId );
					} );
			} );
		}

		$totalMessages = $query->count();
		$sentMessages = ( clone $query )->where( 'status', 'sent' )->count();
		$draftMessages = ( clone $query )->where( 'status', 'draft' )->count();

		// Recent messages (last 7 days)
		$recentMessages = ( clone $query )
			->where( 'created_at', '>=', now()->subDays( 7 ) )
			->count();

		return [ 
			'total_messages'  => $totalMessages,
			'sent_messages'   => $sentMessages,
			'draft_messages'  => $draftMessages,
			'recent_messages' => $recentMessages,
		];
	}
}
