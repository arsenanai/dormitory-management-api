<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Transaction;
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
            'total_debts'          => Payment::sum('amount'),
            'total_collected'      => Transaction::where('status', 'completed')->sum('amount'),
            'total_pending'        => Payment::sum('amount') - Payment::sum('paid_amount'),
            'approved_amount'      => Transaction::where('status', 'completed')->sum('amount'),
            'pending_transactions' => Transaction::where('status', 'processing')->count(),
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

        $studentTransactions = Transaction::where('user_id', $studentId);

        $summary = [
            'total_debts'          => $payments->sum('amount'),
            'total_collected'      => (clone $studentTransactions)->where('status', 'completed')->sum('amount'),
            'total_pending'        => $payments->sum('amount') - $payments->sum('paid_amount'),
            'approved_amount'      => (clone $studentTransactions)->where('status', 'completed')->sum('amount'),
            'pending_transactions' => (clone $studentTransactions)->where('status', 'processing')->count(),
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

        $payments = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        // Scope transaction queries to same date range
        $transactionQuery = Transaction::query();
        if ($request->filled('start_date')) {
            $transactionQuery->whereDate('created_at', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $transactionQuery->whereDate('created_at', '<=', $request->end_date);
        }

        $summary = [
            'total_debts'          => $payments->sum('amount'),
            'total_collected'      => (clone $transactionQuery)->where('status', 'completed')->sum('amount'),
            'total_pending'        => $payments->sum('amount') - $payments->sum('paid_amount'),
            'approved_amount'      => (clone $transactionQuery)->where('status', 'completed')->sum('amount'),
            'pending_transactions' => (clone $transactionQuery)->where('status', 'processing')->count(),
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
        $query = Payment::with([ 'user', 'type' ])
            ->select([
                'payments.*',
                'users.name as student_name',
                'users.email as student_email'
            ])
            ->join('users', 'payments.user_id', '=', 'users.id');

        if ($request->filled('student')) {
            $query->where('users.name', 'like', '%' . $request->student . '%');
        }

        if ($request->filled('start_date')) {
            $query->where('payments.deal_date', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->where('payments.deal_date', '<=', $request->end_date);
        }

        $payments = $query->orderBy('payments.created_at', 'desc')->get();

        $csvContent = "ID,Student,Email,Type,Deal Number,Amount,Paid Amount,Remaining,Status,Date From,Date To\n";
        foreach ($payments as $p) {
            $remaining = $p->amount - ($p->paid_amount ?? 0);
            $csvContent .= sprintf(
                "%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n",
                $p->id,
                '"' . str_replace('"', '""', $p->student_name ?? '') . '"',
                $p->student_email ?? '',
                '"' . str_replace('"', '""', $p->type->name ?? '') . '"',
                '"' . str_replace('"', '""', $p->deal_number ?? '') . '"',
                $p->amount,
                $p->paid_amount ?? 0,
                $remaining,
                $p->status?->value ?? '',
                $p->date_from?->format('Y-m-d') ?? '',
                $p->date_to?->format('Y-m-d') ?? ''
            );
        }

        $filename = 'accounting_export_' . date('Y-m-d_H-i-s') . '.csv';
        return response($csvContent)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    /**
     * Get accounting statistics
     */
    public function stats(Request $request)
    {
        $stats = [
            'total_payments'         => Payment::count(),
            'total_amount'           => Payment::sum('amount'),
            'total_collected'        => Transaction::where('status', 'completed')->sum('amount'),
            'total_pending'          => Payment::sum('amount') - Payment::sum('paid_amount'),
            'approved_transactions'  => Transaction::where('status', 'completed')->count(),
            'pending_transactions'   => Transaction::where('status', 'processing')->count(),
            'students_with_payments' => Payment::distinct('user_id')->count(),
        ];

        return response()->json($stats);
    }
}
