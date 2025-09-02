<?php

namespace App\Http\Controllers;

use App\Services\PaymentService;
use Illuminate\Http\Request;

class PaymentController extends Controller {
	public function __construct( private PaymentService $paymentService ) {
	}

	/**
	 * Display a listing of payments
	 */
	public function index( Request $request ) {
		$filters = $request->validate( [ 
			'user_id'        => 'sometimes|integer|exists:users,id',
			'status'         => 'sometimes|in:pending,completed,failed',
			'payment_method' => 'sometimes|string',
			'date_from'      => 'sometimes|date',
			'date_to'        => 'sometimes|date',
			'per_page'       => 'sometimes|integer|min:1|max:1000',
		] );

		return $this->paymentService->getPaymentsWithFilters( $filters );
	}

	/**
	 * Store a newly created payment
	 */
	public function store( Request $request ) {
		$validated = $request->validate( [ 
			'user_id'         => 'required|integer|exists:users,id',
			'amount'          => 'required|numeric|min:0',
			'contract_number' => 'required|string|max:255',
			'contract_date'   => 'required|date',
			'payment_date'    => 'required|date',
			'payment_method'  => 'required|string|max:100',
			'receipt_file'    => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120', // 5MB
			'status'          => 'sometimes|in:pending,completed,failed',
			'semester'        => 'required|string|max:255',
			'year'            => 'required|integer|min:2020|max:2030',
			'semester_type'   => 'required|in:fall,spring,summer',
			'payment_status'  => 'sometimes|in:pending,approved,rejected,expired',
		] );

		return $this->paymentService->createPayment( $validated );
	}

	/**
	 * Display the specified payment
	 */
	public function show( $id ) {
		return $this->paymentService->getPaymentDetails( $id );
	}

	/**
	 * Update the specified payment
	 */
	public function update( Request $request, $id ) {
		$validated = $request->validate( [ 
			'amount'                    => 'sometimes|numeric|min:0',
			'contract_number'           => 'sometimes|string|max:255',
			'contract_date'             => 'sometimes|date',
			'payment_date'              => 'sometimes|date',
			'payment_method'            => 'sometimes|string|max:100',
			'receipt_file'              => 'sometimes|file|mimes:jpg,jpeg,png,pdf|max:5120',
			'status'                    => 'sometimes|in:pending,completed,failed',
			'payment_status'            => 'sometimes|in:pending,approved,rejected,expired',
			'payment_approved'          => 'sometimes|boolean',
			'dormitory_access_approved' => 'sometimes|boolean',
			// semester fields
			'semester'                  => 'sometimes|string|max:255',
			'year'                      => 'sometimes|integer|min:2020|max:2035',
			'semester_type'             => 'sometimes|in:fall,spring,summer',
		] );

		return $this->paymentService->updatePayment( $id, $validated );
	}

	/**
	 * Remove the specified payment
	 */
	public function destroy( $id ) {
		$this->paymentService->deletePayment( $id );
		return response()->json( [ 'message' => 'Payment deleted successfully' ], 200 );
	}

	/**
	 * Export payments to CSV
	 */
	public function export( Request $request ) {
		$filters = $request->validate( [ 
			'user_id'        => 'sometimes|integer|exists:users,id',
			'status'         => 'sometimes|in:pending,completed,failed',
			'payment_method' => 'sometimes|string',
			'date_from'      => 'sometimes|date',
			'date_to'        => 'sometimes|date',
		] );

		return $this->paymentService->exportPayments( $filters );
	}
}
