<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Models\Bed;
use App\Models\Dormitory;
use App\Models\Message;
use App\Models\Payment;
use App\Models\Room;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class DashboardService
{
    /**
     * Get overall dashboard statistics
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDashboardStats(): \Illuminate\Http\JsonResponse
    {
        $user = Auth::user();

        // Check if user is admin and has a specific dormitory
        $dormitoryFilter = null;
        if ($user->hasRole('admin') && $user->adminDormitory) {
            $dormitoryFilter = $user->adminDormitory->id;
        }

        $studentStats = $this->getStudentStats($dormitoryFilter);
        $roomStats = $this->getRoomStats($dormitoryFilter);
        $paymentStats = $this->getPaymentStats($dormitoryFilter);
        $messageStats = $this->getMessageStats($dormitoryFilter);

        // Get unread messages for current user
        $unreadMessages = Message::where('receiver_id', $user->id)
            ->whereNull('read_at')
            ->count();

        $stats = [
            'total_dormitories'      => $user->hasRole('sudo') ? Dormitory::count() : null,
            'total_rooms'            => $roomStats['total_rooms'],
            'total_beds'             => $roomStats['total_beds'],
            'available_rooms'        => $roomStats['available_rooms'],
            'occupied_rooms'         => $roomStats['occupied_rooms'],
            'available_beds'         => $roomStats['available_beds'],
            'occupied_beds'          => $roomStats['occupied_beds'],
            'total_students'         => $studentStats['total'],
            'students_with_meals'    => $studentStats['with_meals'],
            'students_without_meals' => $studentStats['without_meals'],
            'current_presence'       => $roomStats['occupied_beds'],
            'total_payments'         => $paymentStats['total_payments'],
            'pending_payments'       => $paymentStats['pending_payments'],
            'recent_payments'        => $paymentStats['this_month_amount'],
            'unread_messages'        => $unreadMessages,
            'recent_messages'        => $messageStats['recent_messages'],
            'occupancy_rate'         => $roomStats['occupancy_rate'],
            'quota_students'         => 0,
        ];

        return response()->json($stats);
    }

    /**
     * Get dashboard statistics for a specific dormitory
     *
     * @param  int|null  $dormitoryId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDormitoryStats(?int $dormitoryId): \Illuminate\Http\JsonResponse
    {
        $studentStats = $this->getStudentStats($dormitoryId);
        $roomStats = $this->getRoomStats($dormitoryId);
        $paymentStats = $this->getPaymentStats($dormitoryId);
        $messageStats = $this->getMessageStats($dormitoryId);

        $stats = [
            'total_rooms'            => $roomStats['total_rooms'],
            'total_beds'             => $roomStats['total_beds'],
            'available_rooms'        => $roomStats['available_rooms'],
            'occupied_rooms'         => $roomStats['occupied_rooms'],
            'available_beds'         => $roomStats['available_beds'],
            'occupied_beds'          => $roomStats['occupied_beds'],
            'total_students'         => $studentStats['total'],
            'students_with_meals'    => $studentStats['with_meals'],
            'students_without_meals' => $studentStats['without_meals'],
            'current_presence'       => $roomStats['occupied_beds'],
            'total_payments'         => $paymentStats['total_payments'],
            'pending_payments'       => $paymentStats['pending_payments'],
            'recent_payments'        => $paymentStats['this_month_amount'],
            'recent_messages'        => $messageStats['recent_messages'],
            'occupancy_rate'         => $roomStats['occupancy_rate'],
        ];

        return response()->json($stats);
    }

    /**
     * Get student statistics
     *
     * @param  int|null  $dormitoryId
     * @return array{total: int, with_meals: int, without_meals: int}
     */
    private function getStudentStats(?int $dormitoryId = null): array
    {
        $query = User::with('studentProfile')->whereHas('role', fn ($q) => $q->where('name', 'student'));

        if ($dormitoryId) {
            $query->whereHas('room', fn ($q) => $q->where('dormitory_id', $dormitoryId));
        }

        $totalStudents = $query->count();
        $activeStudents = (clone $query)->where('status', 'active')->count();
        $pendingStudents = (clone $query)->where('status', 'pending')->count();

        // Meal paying = students with at least one catering payment (pending, processing, or completed)
        $studentsWithMeals = (clone $query)
            ->whereHas('payments', fn ($q) => $q->forCatering()->paying())
            ->count();

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
     *
     * @param  int|null  $dormitoryId
     * @return array{total_rooms: int, total_beds: int, available_rooms: int, occupied_rooms: int, available_beds: int, occupied_beds: int, occupancy_rate: float}
     */
    private function getRoomStats(?int $dormitoryId = null): array
    {
        $query = Room::query();

        if ($dormitoryId) {
            $query->where('dormitory_id', $dormitoryId);
        }

        $totalRooms = $query->count();

        // Get bed statistics based on user_id (since tests use this)
        $bedQuery = Bed::whereHas('room', function ($q) use ($dormitoryId) {
            if ($dormitoryId) {
                $q->where('dormitory_id', $dormitoryId);
            }
        });

        $totalBeds = $bedQuery->count();
        $occupiedBeds = (clone $bedQuery)->where(function ($q) {
            $q->whereNotNull('user_id')->orWhere('is_occupied', true);
        })->count();
        $availableBeds = $totalBeds - $occupiedBeds;

        // Room occupancy: use is_occupied flag, or fall back to bed availability
        $occupiedRooms = Room::where('is_occupied', true);
        if ($dormitoryId) {
            $occupiedRooms->where('dormitory_id', $dormitoryId);
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
            'occupancy_rate'  => $totalBeds > 0 ? round(($occupiedBeds / $totalBeds) * 100, 2) : 0.0,
        ];
    }

    /**
     * Get payment statistics
     *
     * @param  int|null  $dormitoryId
     * @return array{total_payments: int, pending_payments: int, completed_payments: int, this_month_amount: float, pending_transactions: int}
     */
    private function getPaymentStats(?int $dormitoryId = null): array
    {
        $paymentQuery = Payment::query();
        $transactionQuery = Transaction::query();

        if ($dormitoryId) {
            $paymentQuery->whereHas('user.room', fn ($q) => $q->where('dormitory_id', $dormitoryId));
            $transactionQuery->whereHas('user.room', fn ($q) => $q->where('dormitory_id', $dormitoryId));
        }

        $totalPayments = $paymentQuery->count();
        $totalAmount = $paymentQuery->sum('amount');
        $pendingPayments = (clone $paymentQuery)->whereIn('status', [
            PaymentStatus::Pending,
            PaymentStatus::PartiallyPaid,
        ])->count();
        $completedPayments = (clone $paymentQuery)->where('status', PaymentStatus::Completed)->count();

        $totalCollected = (clone $transactionQuery)->where('status', 'completed')->sum('amount');
        $pendingTransactions = (clone $transactionQuery)->where('status', 'processing')->count();

        $thisMonthAmount = (clone $transactionQuery)
            ->where('status', 'completed')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('amount');

        return [
            'total_payments'       => $totalPayments,
            'total_amount'         => $totalAmount,
            'total_collected'      => $totalCollected,
            'completed_payments'   => $completedPayments,
            'pending_payments'     => $pendingPayments,
            'pending_transactions' => $pendingTransactions,
            'this_month_amount'    => $thisMonthAmount,
        ];
    }

    /**
     * Get message statistics
     *
     * @param  int|null  $dormitoryId
     * @return array{total_messages: int, sent_messages: int, draft_messages: int, recent_messages: int}
     */
    private function getMessageStats(?int $dormitoryId = null): array
    {
        $query = Message::query();

        if ($dormitoryId) {
            $query->where(function ($q) use ($dormitoryId) {
                $q->where('recipient_type', 'all')
                    ->orWhere(function ($subQ) use ($dormitoryId) {
                        $subQ->where('recipient_type', 'dormitory')
                            ->where('dormitory_id', $dormitoryId);
                    });
            });
        }

        $totalMessages = $query->count();
        $sentMessages = (clone $query)->where('status', 'sent')->count();
        $draftMessages = (clone $query)->where('status', 'draft')->count();

        // Recent messages (last 7 days)
        $recentMessages = (clone $query)
            ->where('created_at', '>=', now()->subDays(7))
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
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getGuardStats(): \Illuminate\Http\JsonResponse
    {
        $totalRooms = Room::count();
        $occupiedRooms = Room::where('is_occupied', true)->count();

        $stats = [
            'total_rooms'       => $totalRooms,
            'occupied_rooms'    => $occupiedRooms,
            'my_reports'        => Message::where('type', 'violation')->count(),
            'recent_violations' => Message::where('type', 'violation')
                ->where('created_at', '>=', now()->subDays(7))
                ->count(),
            'room_occupancy'    => $totalRooms > 0 ? round(($occupiedRooms / $totalRooms) * 100, 2) : 0,
        ];

        return response()->json($stats);
    }

    /**
     * Get student dashboard statistics
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getStudentDashboardStats(): \Illuminate\Http\JsonResponse
    {
        $user = Auth::user();

        // Get user's room information
        $roomInfo = null;
        if ($user->room) {
            $roomInfo = [
                'room_number'    => $user->room->number,
                'floor'          => $user->room->floor,
                'dormitory_name' => $user->room->dormitory->name ?? 'Unknown',
                'room_type'      => $user->room->roomType->name ?? 'standard',
            ];
        }

        // Get user's messages
        $messages = Message::where('receiver_id', $user->id)
            ->with('sender')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // Get user's payments
        $payments = Payment::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        $stats = [
            'my_messages'           => $messages->count(),
            'unread_messages_count' => $messages->whereNull('read_at')->count(),
            'my_payments'           => $payments->count(),
            'upcoming_payments'     => $payments->whereIn('status', [PaymentStatus::Pending, PaymentStatus::PartiallyPaid])->count(),
            'payment_history'       => $payments->count(), // All payments are history
            'room_info'             => $roomInfo,
        ];

        return response()->json($stats);
    }

    /**
     * Get guest dashboard statistics
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getGuestDashboardStats(): \Illuminate\Http\JsonResponse
    {
        $user = Auth::user();

        // Get user's room information
        $roomInfo = null;
        if ($user->room) {
            $roomInfo = [
                'room_number'    => $user->room->number,
                'floor'          => $user->room->floor,
                'dormitory_name' => $user->room->dormitory->name ?? 'Unknown',
                'room_type'      => $user->room->roomType->name ?? 'standard',
            ];
        }

        // Get user's messages
        $messages = Message::where('receiver_id', $user->id)
            ->with('sender')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // Mock guest-specific data (since guest payments might be handled differently)
        $stats = [
            'my_messages'           => $messages->count(),
            'unread_messages_count' => $messages->whereNull('read_at')->count(),
            'room_info'             => $roomInfo,
            'daily_rate'            => 5000, // Mock daily rate in tenge
            'check_in_date'         => now()->format('Y-m-d'),
            'check_out_date'        => now()->addDays(5)->format('Y-m-d'),
            'total_days'            => 5,
            'total_amount'          => 25000, // Mock total amount
        ];

        return response()->json($stats);
    }

    /**
     * Get monthly statistics
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getMonthlyStats(): \Illuminate\Http\JsonResponse
    {
        $currentMonth = [
            'total_payments'    => Payment::whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)->count(),
            'total_amount'      => Transaction::where('status', 'completed')
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)->sum('amount'),
            'approved_payments' => Transaction::where('status', 'completed')
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)->count(),
            'new_students'      => User::whereHas('role', fn ($q) => $q->where('name', 'student'))
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)->count(),
            'messages_sent'     => Message::whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)->count(),
        ];

        $lastMonth = [
            'total_payments'    => Payment::whereMonth('created_at', now()->subMonth()->month)
                ->whereYear('created_at', now()->subMonth()->year)->count(),
            'total_amount'      => Transaction::where('status', 'completed')
                ->whereMonth('created_at', now()->subMonth()->month)
                ->whereYear('created_at', now()->subMonth()->year)->sum('amount'),
            'approved_payments' => Transaction::where('status', 'completed')
                ->whereMonth('created_at', now()->subMonth()->month)
                ->whereYear('created_at', now()->subMonth()->year)->count(),
            'new_students'      => User::whereHas('role', fn ($q) => $q->where('name', 'student'))
                ->whereMonth('created_at', now()->subMonth()->month)
                ->whereYear('created_at', now()->subMonth()->year)->count(),
            'messages_sent'     => Message::whereMonth('created_at', now()->subMonth()->month)
                ->whereYear('created_at', now()->subMonth()->year)->count(),
        ];

        // Get monthly revenue for the last 12 months (from completed transactions)
        $monthlyRevenue = [];
        for ($i = 11; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $monthlyRevenue[] = [
                'month'         => $date->format('M'),
                'year'          => $date->year,
                'total_amount'  => Transaction::where('status', 'completed')
                    ->whereMonth('created_at', $date->month)
                    ->whereYear('created_at', $date->year)->sum('amount') ?: 0,
                'payment_count' => Transaction::where('status', 'completed')
                    ->whereMonth('created_at', $date->month)
                    ->whereYear('created_at', $date->year)->count(),
            ];
        }

        return response()->json([
            'current_month'   => $currentMonth,
            'previous_month'  => $lastMonth,
            'monthly_revenue' => $monthlyRevenue,
        ]);
    }

    /**
     * Get payment analytics
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPaymentAnalytics(): \Illuminate\Http\JsonResponse
    {
        $paymentMethods = Transaction::where('status', 'completed')
            ->selectRaw('payment_method, COUNT(*) as count, SUM(amount) as total')
            ->groupBy('payment_method')
            ->get();

        $paymentStatuses = Transaction::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->get();

        // Get daily revenue from completed transactions for last 30 days
        $dailyRevenue = [];
        for ($i = 29; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $dayData = Transaction::where('status', 'completed')
                ->whereDate('created_at', $date)
                ->selectRaw('SUM(amount) as total_amount, COUNT(*) as payment_count')
                ->first();

            $dailyRevenue[] = [
                'date'          => $date,
                'total_amount'  => (float) ($dayData?->total_amount ?? 0),
                'payment_count' => $dayData?->payment_count ?? 0,
            ];
        }

        return response()->json([
            'payment_methods'  => $paymentMethods,
            'payment_statuses' => $paymentStatuses,
            'daily_revenue'    => $dailyRevenue,
        ]);
    }

    /**
     * Get detailed dashboard statistics (nested structure)
     */
    public function getDetailedDashboardStats()
    {
        $user = Auth::user();

        // Check if user is admin and has a specific dormitory
        $dormitoryFilter = null;
        if ($user->hasRole('admin') && $user->adminDormitory) {
            $dormitoryFilter = $user->adminDormitory->id;
        }

        $stats = [
            'students' => $this->getStudentStats($dormitoryFilter),
            'rooms'    => $this->getRoomStats($dormitoryFilter),
            'payments' => $this->getPaymentStats($dormitoryFilter),
            'messages' => $this->getMessageStats($dormitoryFilter),
        ];

        return response()->json($stats);
    }
}
