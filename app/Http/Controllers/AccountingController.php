<?php

namespace App\Http\Controllers;

use App\Models\Payment; // Changed from SemesterPayment to Payment
use Illuminate\Http\Request;

class AccountingController extends Controller
{
    /**
     * Get accounting overview data
     */
    public function index(Request $request)
    {
        $query = Payment::with([ 'user' ])
            ->select([
                'payments.*', // Changed table alias
                'users.name as student_name',
                'users.email as student_email'
            ])
            ->join('users', 'payments.user_id', '=', 'users.id'); // Changed table alias

        // Apply filters
        if ($request->filled('student')) {
            $query->where('users.name', 'like', '%' . $request->student . '%');
        }

        // 'semester' column was dropped. Filtering by date range is more appropriate now.
        // If a 'semester' filter is still desired, it needs to be re-implemented based on date_from/date_to.
        // For now, removing the filter to prevent errors.

        if ($request->filled('start_date')) {
            $query->where('payments.deal_date', '>=', $request->start_date); // Assuming deal_date is the relevant date
        }

        if ($request->filled('end_date')) {
            $query->where('payments.deal_date', '<=', $request->end_date); // Assuming deal_date is the relevant date
        }

        // Get paginated results
        $payments = $query->orderBy('payments.created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        // Calculate summary statistics
        $summary = [
            'total_payments'  => $payments->total(),
            'total_amount'    => $payments->sum('amount'),
            // 'payment_approved' column was dropped. These summaries need re-evaluation.
            'approved_amount' => 0, // Placeholder
            'pending_amount'  => 0, // Placeholder
        ];

        return response()->json([
            'data'       => $payments->items(),
            'pagination' => [
                'current_page' => $payments->currentPage(),
                'last_page'    => $payments->lastPage(),
                'per_page'     => $payments->perPage(),
                'total'        => $payments->total(),
            ],
            'summary'    => $summary,
        ]);
    }

    /**
     * Get accounting data for a specific student
     */
    public function studentAccounting($studentId, Request $request)
    {
        $payments = Payment::where('user_id', $studentId)
            ->with([ 'user' ])
            ->orderBy('created_at', 'desc')
            ->get();

        $summary = [
            'total_payments'  => $payments->count(),
            'total_amount'    => $payments->sum('amount'),
            'approved_amount' => 0, // Placeholder
            'pending_amount'  => 0, // Placeholder
        ];

        return response()->json([
            'payments' => $payments,
            'summary'  => $summary,
        ]);
    }

    /**
     * Get accounting data for a specific semester
     */
    public function semesterAccounting($semester, Request $request)
    {
        $query = Payment::with([ 'user' ]);
        // 'semester' column was dropped. This method needs re-implementation based on date ranges.

        // Apply additional filters
        if ($request->filled('start_date')) {
            $query->where('deal_date', '>=', $request->start_date); // Assuming deal_date is the relevant date
        }

        if ($request->filled('end_date')) {
            $query->where('deal_date', '<=', $request->end_date); // Assuming deal_date is the relevant date
        }

        $payments = $query->orderBy('created_at', 'desc') // Order by created_at is fine
            ->paginate($request->get('per_page', 15));

        $summary = [
            'total_payments'  => $payments->total(),
            'total_amount'    => $payments->sum('amount'),
            'approved_amount' => 0, // Placeholder
            'pending_amount'  => 0, // Placeholder
        ];

        return response()->json([
            'data'       => $payments->items(),
            'pagination' => [
                'current_page' => $payments->currentPage(),
                'last_page'    => $payments->lastPage(),
                'per_page'     => $payments->perPage(),
                'total'        => $payments->total(),
            ],
            'summary'    => $summary,
        ]);
    }

    /**
     * Export accounting data
     */
    public function export(Request $request)
    {
        $query = Payment::with([ 'user' ])
            ->select([
                'payments.*', // Changed table alias
                'users.name as student_name',
                'users.email as student_email'
            ])
            ->join('users', 'payments.user_id', '=', 'users.id'); // Changed table alias

        // Apply filters
        if ($request->filled('student')) {
            $query->where('users.name', 'like', '%' . $request->student . '%');
        }

        // 'semester' column was dropped. Removing filter.

        if ($request->filled('start_date')) {
            $query->where('payments.deal_date', '>=', $request->start_date); // Assuming deal_date is the relevant date
        }

        if ($request->filled('end_date')) {
            $query->where('payments.deal_date', '<=', $request->end_date); // Assuming deal_date is the relevant date
        }

        $payments = $query->orderBy('payments.created_at', 'desc')->get(); // Changed table alias

        // For now, return JSON. In a real implementation, you'd generate an Excel file
        return response()->json([
            'message' => 'Export functionality would generate Excel file here',
            'data'    => $payments,
        ]);
    }

    /**
     * Get accounting statistics
     */
    public function stats(Request $request)
    {
        $stats = [
            'total_payments'         => Payment::count(),
            'total_amount'           => Payment::sum('amount'),
            'approved_payments'      => 0, // 'payment_approved' column was dropped. Placeholder.
            'approved_amount'        => 0, // Placeholder
            'pending_payments'       => 0, // Placeholder
            'pending_amount'         => 0, // Placeholder
            'students_with_payments' => Payment::distinct('user_id')->count(),
        ];

        return response()->json($stats);
    }
}
