<?php

namespace App\Services;

use App\Models\User;
use App\Models\Room;
use App\Models\Bed;
use App\Models\Dormitory;
use App\Models\SemesterPayment;
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

		$studentStats = $this->getStudentStats( $dormitoryFilter );
		$roomStats = $this->getRoomStats( $dormitoryFilter );
		$paymentStats = $this->getPaymentStats( $dormitoryFilter );
		$messageStats = $this->getMessageStats( $dormitoryFilter );

		// Get unread messages for current user
		$unreadMessages = Message::where( 'receiver_id', $user->id )
			->whereNull( 'read_at' )
			->count();

		// Return flattened structure for the main dashboard
		$stats = [ 
			'total_students'   => $studentStats['total'],
			'total_rooms'      => $roomStats['total_rooms'],
			'occupied_rooms'   => $roomStats['occupied_rooms'],
			'available_rooms'  => $roomStats['available_rooms'],
			'total_payments'   => $paymentStats['total_payments'],
			'pending_payments' => $paymentStats['pending_payments'],
			'recent_payments'  => $paymentStats['this_month_amount'],
			'unread_messages'  => $unreadMessages,
			'recent_messages'  => $messageStats['recent_messages'],
			'occupancy_rate'   => $roomStats['occupancy_rate'],
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

		// Get bed statistics based on user_id (since tests use this)
		$bedQuery = Bed::whereHas( 'room', function ($q) use ($dormitoryId) {
			if ( $dormitoryId ) {
				$q->where( 'dormitory_id', $dormitoryId );
			}
		} );

		$totalBeds = $bedQuery->count();
		$occupiedBeds = ( clone $bedQuery )->where(function($q) {
			$q->whereNotNull( 'user_id' )->orWhere( 'is_occupied', true );
		})->count();
		$availableBeds = $totalBeds - $occupiedBeds;

		// Room occupancy: use is_occupied flag, or fall back to bed availability
		$occupiedRooms = Room::where( 'is_occupied', true );
		if ( $dormitoryId ) {
			$occupiedRooms->where( 'dormitory_id', $dormitoryId );
		}
		$occupiedRoomsCount = $occupiedRooms->count();
		
		$availableRoomsCount = $totalRooms - $occupiedRoomsCount;

		return [ 
			'total_rooms'     => $totalRooms,
			'available_rooms' => $availableRoomsCount,
			'occupied_rooms'  => $occupiedRoomsCount,
			'total_beds'      => $totalBeds,
			'occupied_beds'   => $occupiedBeds,
			'available_beds'  => $availableBeds,
			'occupancy_rate'  => $totalBeds > 0 ? round( ( $occupiedBeds / $totalBeds ) * 100, 2 ) : 0.0,
		];
	}

	/**
	 * Get payment statistics
	 */
	private function getPaymentStats( $dormitoryId = null ) {
		$query = SemesterPayment::query();

		if ( $dormitoryId ) {
			$query->whereHas( 'user.room', fn( $q ) => $q->where( 'dormitory_id', $dormitoryId ) );
		}

		$totalPayments = $query->count();
		$totalAmount = $query->sum( 'amount' );
		$approvedPayments = ( clone $query )->where( 'payment_approved', true )->count();
		$completedPayments = ( clone $query )->where( 'payment_approved', true )->count(); // Same as approved for now
		$pendingPayments = ( clone $query )->where( 'payment_approved', false )->count();

		// This month's payments
		$thisMonthAmount = ( clone $query )
			->where( 'payment_approved', true )
			->whereMonth( 'created_at', now()->month )
			->whereYear( 'created_at', now()->year )
			->sum( 'amount' );

		return [ 
			'total_payments'     => $totalPayments,
			'total_amount'       => $totalAmount,
			'approved_payments'  => $approvedPayments,
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

	/**
	 * Get guard dashboard stats
	 */
	public function getGuardStats() {
		$totalRooms = Room::count();
		$occupiedRooms = Room::where('is_occupied', true)->count();
		
		$stats = [
			'total_rooms' => $totalRooms,
			'occupied_rooms' => $occupiedRooms,
			'my_reports' => Message::where('type', 'violation')->count(),
			'recent_violations' => Message::where('type', 'violation')
				->where('created_at', '>=', now()->subDays(7))
				->count(),
			'room_occupancy' => $totalRooms > 0 ? round(($occupiedRooms / $totalRooms) * 100, 2) : 0,
		];

		return response()->json($stats);
	}

	/**
	 * Get student dashboard stats
	 */
	public function getStudentDashboardStats() {
		$user = auth()->user();
		
		// Get upcoming payments (payments with due dates in the future)
		$upcomingPayments = SemesterPayment::where('user_id', $user->id)
			->where('payment_approved', false)
			->where('due_date', '>', now())
			->count();

		// Get payment history (all payments for this user)
		$paymentHistory = SemesterPayment::where('user_id', $user->id)->count();

		// Get room info if user has a room
		$roomInfo = null;
		if ($user->room_id) {
			$room = $user->room()->with(['dormitory', 'roomType'])->first();
			if ($room) {
				$roomInfo = [
					'room_number' => $room->number,
					'floor' => $room->floor,
					'dormitory_name' => $room->dormitory->name ?? null,
					'room_type' => $room->roomType->name ?? null,
				];
			}
		}
		
		$stats = [
			'my_messages' => Message::where('receiver_id', $user->id)->count(),
			'unread_messages_count' => Message::where('receiver_id', $user->id)
				->whereNull('read_at')->count(),
			'my_payments' => SemesterPayment::where('user_id', $user->id)->count(),
			'upcoming_payments' => $upcomingPayments,
			'payment_history' => $paymentHistory,
			'room_info' => $roomInfo,
		];

		return response()->json($stats);
	}

	/**
	 * Get monthly statistics
	 */
	public function getMonthlyStats() {
		$currentMonth = [
			'total_payments' => SemesterPayment::whereMonth('created_at', now()->month)
				->whereYear('created_at', now()->year)->count(),
			'total_amount' => SemesterPayment::whereMonth('created_at', now()->month)
				->whereYear('created_at', now()->year)->sum('amount'),
			'approved_payments' => SemesterPayment::whereMonth('created_at', now()->month)
				->whereYear('created_at', now()->year)
				->where('payment_approved', true)->count(),
			'new_students' => User::whereHas('role', fn($q) => $q->where('name', 'student'))
				->whereMonth('created_at', now()->month)
				->whereYear('created_at', now()->year)->count(),
			'messages_sent' => Message::whereMonth('created_at', now()->month)
				->whereYear('created_at', now()->year)->count(),
		];

		$lastMonth = [
			'total_payments' => SemesterPayment::whereMonth('created_at', now()->subMonth()->month)
				->whereYear('created_at', now()->subMonth()->year)->count(),
			'total_amount' => SemesterPayment::whereMonth('created_at', now()->subMonth()->month)
				->whereYear('created_at', now()->subMonth()->year)->sum('amount'),
			'approved_payments' => SemesterPayment::whereMonth('created_at', now()->subMonth()->month)
				->whereYear('created_at', now()->subMonth()->year)
				->where('payment_approved', true)->count(),
			'new_students' => User::whereHas('role', fn($q) => $q->where('name', 'student'))
				->whereMonth('created_at', now()->subMonth()->month)
				->whereYear('created_at', now()->subMonth()->year)->count(),
			'messages_sent' => Message::whereMonth('created_at', now()->subMonth()->month)
				->whereYear('created_at', now()->subMonth()->year)->count(),
		];

		// Get monthly revenue for the last 12 months
		$monthlyRevenue = [];
		for ($i = 11; $i >= 0; $i--) {
			$date = now()->subMonths($i);
			$monthlyRevenue[] = [
				'month' => $date->format('M'),
				'year' => $date->year,
				'total_amount' => SemesterPayment::whereMonth('created_at', $date->month)
					->whereYear('created_at', $date->year)->sum('amount') ?: 0,
				'payment_count' => SemesterPayment::whereMonth('created_at', $date->month)
					->whereYear('created_at', $date->year)->count(),
			];
		}

		return response()->json([
			'current_month' => $currentMonth,
			'previous_month' => $lastMonth,
			'monthly_revenue' => $monthlyRevenue,
		]);
	}

	/**
	 * Get payment analytics
	 */
	public function getPaymentAnalytics() {
		// Get payment methods analytics
		$paymentMethods = SemesterPayment::selectRaw('payment_method as method, COUNT(*) as count, SUM(amount) as total_amount')
			->groupBy('payment_method')
			->get()
			->map(function ($item) {
				return [
					'method' => $item->method,
					'count' => $item->count,
					'total_amount' => (float) $item->total_amount,
				];
			});

		// Get payment statuses analytics
		$paymentStatuses = SemesterPayment::selectRaw('payment_status as status, COUNT(*) as count, SUM(amount) as total_amount')
			->groupBy('payment_status')
			->get()
			->map(function ($item) {
				return [
					'status' => $item->status,
					'count' => $item->count,
					'total_amount' => (float) $item->total_amount,
				];
			});

		// Get daily revenue for the last 30 days
		$dailyRevenue = [];
		for ($i = 29; $i >= 0; $i--) {
			$date = now()->subDays($i)->format('Y-m-d');
			$dayData = SemesterPayment::whereDate('created_at', $date)
				->selectRaw('SUM(amount) as total_amount, COUNT(*) as payment_count')
				->first();
			
			$dailyRevenue[] = [
				'date' => $date,
				'total_amount' => (float) ($dayData->total_amount ?: 0),
				'payment_count' => $dayData->payment_count ?: 0,
			];
		}

		return response()->json([
			'payment_methods' => $paymentMethods,
			'payment_statuses' => $paymentStatuses,
			'daily_revenue' => $dailyRevenue,
		]);
	}

	/**
	 * Get detailed dashboard statistics (nested structure)
	 */
	public function getDetailedDashboardStats() {
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
}
