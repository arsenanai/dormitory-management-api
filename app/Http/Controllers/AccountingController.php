<?php

namespace App\Http\Controllers;

use App\Models\SemesterPayment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccountingController extends Controller {
	/**
	 * Get accounting overview data
	 */
	public function index( Request $request ) {
		$query = SemesterPayment::with( [ 'user' ] )
			->select( [ 
				'semester_payments.*',
				'users.name as student_name',
				'users.email as student_email'
			] )
			->join( 'users', 'semester_payments.user_id', '=', 'users.id' );

		// Apply filters
		if ( $request->filled( 'student' ) ) {
			$query->where( 'users.name', 'like', '%' . $request->student . '%' );
		}

		if ( $request->filled( 'semester' ) ) {
			$query->where( 'semester_payments.semester', $request->semester );
		}

		if ( $request->filled( 'start_date' ) ) {
			$query->where( 'semester_payments.created_at', '>=', $request->start_date );
		}

		if ( $request->filled( 'end_date' ) ) {
			$query->where( 'semester_payments.created_at', '<=', $request->end_date );
		}

		// Get paginated results
		$payments = $query->orderBy( 'semester_payments.created_at', 'desc' )
			->paginate( $request->get( 'per_page', 15 ) );

		// Calculate summary statistics
		$summary = [ 
			'total_payments'  => $payments->total(),
			'total_amount'    => $payments->sum( 'amount' ),
			'approved_amount' => $payments->where( 'payment_approved', true )->sum( 'amount' ),
			'pending_amount'  => $payments->where( 'payment_approved', false )->sum( 'amount' ),
		];

		return response()->json( [ 
			'data'       => $payments->items(),
			'pagination' => [ 
				'current_page' => $payments->currentPage(),
				'last_page'    => $payments->lastPage(),
				'per_page'     => $payments->perPage(),
				'total'        => $payments->total(),
			],
			'summary'    => $summary,
		] );
	}

	/**
	 * Get accounting data for a specific student
	 */
	public function studentAccounting( $studentId, Request $request ) {
		$payments = SemesterPayment::where( 'user_id', $studentId )
			->with( [ 'user' ] )
			->orderBy( 'created_at', 'desc' )
			->get();

		$summary = [ 
			'total_payments'  => $payments->count(),
			'total_amount'    => $payments->sum( 'amount' ),
			'approved_amount' => $payments->where( 'payment_approved', true )->sum( 'amount' ),
			'pending_amount'  => $payments->where( 'payment_approved', false )->sum( 'amount' ),
		];

		return response()->json( [ 
			'payments' => $payments,
			'summary'  => $summary,
		] );
	}

	/**
	 * Get accounting data for a specific semester
	 */
	public function semesterAccounting( $semester, Request $request ) {
		$query = SemesterPayment::with( [ 'user' ] )
			->where( 'semester', $semester );

		// Apply additional filters
		if ( $request->filled( 'start_date' ) ) {
			$query->where( 'created_at', '>=', $request->start_date );
		}

		if ( $request->filled( 'end_date' ) ) {
			$query->where( 'created_at', '<=', $request->end_date );
		}

		$payments = $query->orderBy( 'created_at', 'desc' )
			->paginate( $request->get( 'per_page', 15 ) );

		$summary = [ 
			'total_payments'  => $payments->total(),
			'total_amount'    => $payments->sum( 'amount' ),
			'approved_amount' => $payments->where( 'payment_approved', true )->sum( 'amount' ),
			'pending_amount'  => $payments->where( 'payment_approved', false )->sum( 'amount' ),
		];

		return response()->json( [ 
			'data'       => $payments->items(),
			'pagination' => [ 
				'current_page' => $payments->currentPage(),
				'last_page'    => $payments->lastPage(),
				'per_page'     => $payments->perPage(),
				'total'        => $payments->total(),
			],
			'summary'    => $summary,
		] );
	}

	/**
	 * Export accounting data
	 */
	public function export( Request $request ) {
		$query = SemesterPayment::with( [ 'user' ] )
			->select( [ 
				'semester_payments.*',
				'users.name as student_name',
				'users.email as student_email'
			] )
			->join( 'users', 'semester_payments.user_id', '=', 'users.id' );

		// Apply filters
		if ( $request->filled( 'student' ) ) {
			$query->where( 'users.name', 'like', '%' . $request->student . '%' );
		}

		if ( $request->filled( 'semester' ) ) {
			$query->where( 'semester_payments.semester', $request->semester );
		}

		if ( $request->filled( 'start_date' ) ) {
			$query->where( 'semester_payments.created_at', '>=', $request->start_date );
		}

		if ( $request->filled( 'end_date' ) ) {
			$query->where( 'semester_payments.created_at', '<=', $request->end_date );
		}

		$payments = $query->orderBy( 'semester_payments.created_at', 'desc' )->get();

		// For now, return JSON. In a real implementation, you'd generate an Excel file
		return response()->json( [ 
			'message' => 'Export functionality would generate Excel file here',
			'data'    => $payments,
		] );
	}

	/**
	 * Get accounting statistics
	 */
	public function stats( Request $request ) {
		$stats = [ 
			'total_payments'         => SemesterPayment::count(),
			'total_amount'           => SemesterPayment::sum( 'amount' ),
			'approved_payments'      => SemesterPayment::where( 'payment_approved', true )->count(),
			'approved_amount'        => SemesterPayment::where( 'payment_approved', true )->sum( 'amount' ),
			'pending_payments'       => SemesterPayment::where( 'payment_approved', false )->count(),
			'pending_amount'         => SemesterPayment::where( 'payment_approved', false )->sum( 'amount' ),
			'students_with_payments' => SemesterPayment::distinct( 'user_id' )->count(),
		];

		return response()->json( $stats );
	}
}
