<?php

namespace App\Services;

use App\Models\CardReaderLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CardReaderService
{
    /**
     * Log a card reader entry/exit
     */
    public function logCardReaderEntry(array $data)
    {
        $user = User::where('card_number', $data['card_number'])->first();

        if (! $user) {
            return response()->json([ 'error' => 'User not found for card number' ], 404);
        }

        // Check if this is an entry or exit
        $latestLog = CardReaderLog::where('user_id', $user->id)
            ->where('location', $data['location'])
            ->latest('entry_time')
            ->first();

        $action = 'entry';
        if ($latestLog && $latestLog->action === 'entry' && is_null($latestLog->exit_time)) {
            // User is currently inside, this is an exit
            $action = 'exit';
            $latestLog->update([
                'exit_time' => now(),
                'action'    => 'exit'
            ]);
        } else {
            // This is an entry
            CardReaderLog::create([
                'user_id'     => $user->id,
                'card_number' => $data['card_number'],
                'entry_time'  => now(),
                'location'    => $data['location'],
                'action'      => 'entry'
            ]);
        }

        return response()->json([
            'success'   => true,
            'user'      => $user->only([ 'id', 'name', 'email' ]),
            'action'    => $action,
            'location'  => $data['location'],
            'timestamp' => now()->toDateTimeString()
        ]);
    }

    /**
     * Get current presence status for a user
     */
    public function getUserPresenceStatus($userId)
    {
        $user = User::findOrFail($userId);
        $latestLog = CardReaderLog::where('user_id', $userId)
            ->latest('entry_time')
            ->first();

        $isInside = false;
        $lastActivity = null;
        $location = null;

        if ($latestLog) {
            $isInside = $latestLog->action === 'entry' && is_null($latestLog->exit_time);
            $lastActivity = $latestLog->entry_time;
            $location = $latestLog->location;
        }

        return [
            'user'          => $user->only([ 'id', 'name', 'email' ]),
            'is_inside'     => $isInside,
            'last_activity' => $lastActivity,
            'location'      => $location,
            'logs'          => CardReaderLog::where('user_id', $userId)
                ->orderBy('entry_time', 'desc')
                ->limit(10)
                ->get()
        ];
    }

    /**
     * Get all users currently inside
     */
    public function getUsersCurrentlyInside($location = null)
    {
        $query = CardReaderLog::with('user')
            ->where('action', 'entry')
            ->whereNull('exit_time');

        if ($location) {
            $query->where('location', $location);
        }

        return $query->get();
    }

    /**
     * Get card reader logs with filters
     */
    public function getCardReaderLogs(array $filters = [])
    {
        $query = CardReaderLog::with('user');

        if (isset($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (isset($filters['location'])) {
            $query->where('location', $filters['location']);
        }

        if (isset($filters['action'])) {
            $query->where('action', $filters['action']);
        }

        if (isset($filters['date_from'])) {
            $query->whereDate('entry_time', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->whereDate('entry_time', '<=', $filters['date_to']);
        }

        $perPage = $filters['per_page'] ?? 50;
        return $query->orderBy('entry_time', 'desc')->paginate($perPage);
    }

    /**
     * Get daily attendance report
     */
    public function getDailyAttendanceReport($date = null)
    {
        $date = $date ? Carbon::parse($date) : now();

        $logs = CardReaderLog::with('user')
            ->whereDate('entry_time', $date)
            ->orderBy('entry_time', 'asc')
            ->get();

        $report = [];
        $userStats = [];

        foreach ($logs as $log) {
            $userId = $log->user_id;
            $userName = $log->user->name ?? 'Unknown';

            if (! isset($userStats[ $userId ])) {
                $userStats[ $userId ] = [
                    'user_id'           => $userId,
                    'user_name'         => $userName,
                    'entries'           => 0,
                    'exits'             => 0,
                    'total_time_inside' => 0,
                    'current_status'    => 'outside',
                    'first_entry'       => null,
                    'last_activity'     => null
                ];
            }

            if ($log->action === 'entry') {
                $userStats[ $userId ]['entries']++;
                $userStats[ $userId ]['current_status'] = 'inside';
                if (! $userStats[ $userId ]['first_entry']) {
                    $userStats[ $userId ]['first_entry'] = $log->entry_time;
                }
            } else {
                $userStats[ $userId ]['exits']++;
                $userStats[ $userId ]['current_status'] = 'outside';
            }

            $userStats[ $userId ]['last_activity'] = $log->entry_time;
        }

        return [
            'date'        => $date->toDateString(),
            'total_users' => count($userStats),
            'users_stats' => array_values($userStats),
            'summary'     => [
                'total_entries'          => $logs->where('action', 'entry')->count(),
                'total_exits'            => $logs->where('action', 'exit')->count(),
                'users_currently_inside' => collect($userStats)->where('current_status', 'inside')->count()
            ]
        ];
    }

    /**
     * Get monthly attendance statistics
     */
    public function getMonthlyAttendanceStats($month = null, $year = null)
    {
        $month = $month ?? now()->month;
        $year = $year ?? now()->year;

        $stats = DB::table('card_reader_logs')
            ->join('users', 'card_reader_logs.user_id', '=', 'users.id')
            ->whereMonth('entry_time', $month)
            ->whereYear('entry_time', $year)
            ->select([
                DB::raw('DATE(entry_time) as date'),
                DB::raw('COUNT(DISTINCT user_id) as unique_users'),
                DB::raw('COUNT(*) as total_activities'),
                DB::raw('SUM(CASE WHEN action = "entry" THEN 1 ELSE 0 END) as entries'),
                DB::raw('SUM(CASE WHEN action = "exit" THEN 1 ELSE 0 END) as exits')
            ])
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return [
            'month'       => $month,
            'year'        => $year,
            'daily_stats' => $stats,
            'summary'     => [
                'total_days'       => $stats->count(),
                'avg_daily_users'  => $stats->avg('unique_users'),
                'total_activities' => $stats->sum('total_activities'),
                'total_entries'    => $stats->sum('entries'),
                'total_exits'      => $stats->sum('exits')
            ]
        ];
    }

    /**
     * Export attendance report to CSV
     */
    public function exportAttendanceReport(array $filters = [])
    {
        $logs = CardReaderLog::with('user');

        if (isset($filters['date_from'])) {
            $logs->whereDate('entry_time', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $logs->whereDate('entry_time', '<=', $filters['date_to']);
        }

        if (isset($filters['location'])) {
            $logs->where('location', $filters['location']);
        }

        $logs = $logs->orderBy('entry_time', 'desc')->get();

        $csvContent = "User Name,User Email,Card Number,Action,Location,Entry Time,Exit Time\n";

        foreach ($logs as $log) {
            $csvContent .= sprintf(
                "%s,%s,%s,%s,%s,%s,%s\n",
                $log->user->name ?? 'Unknown',
                $log->user->email ?? '',
                $log->card_number,
                $log->action,
                $log->location,
                $log->entry_time,
                $log->exit_time ?? ''
            );
        }

        $fileName = 'attendance_report_' . now()->format('Y_m_d_H_i_s') . '.csv';
        $filePath = 'exports/' . $fileName;

        \Storage::disk('public')->put($filePath, $csvContent);

        return response()->download(storage_path('app/public/' . $filePath), $fileName, [
            'Content-Type' => 'text/csv'
        ]);
    }

    /**
     * Sync card reader with external system
     */
    public function syncCardReader()
    {
        // This would integrate with actual card reader hardware/software
        // For now, return a mock response
        return [
            'success'           => true,
            'message'           => 'Card reader sync completed',
            'timestamp'         => now()->toDateTimeString(),
            'devices_synced'    => 3,
            'records_processed' => 150
        ];
    }
}
