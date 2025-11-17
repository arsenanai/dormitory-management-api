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
			'date_from'      => 'sometimes|date',
			'date_to'        => 'sometimes|date',
			'search'         => 'sometimes|nullable|string|max:255', // Searches deal_number or user name
			'role'           => 'sometimes|nullable|string|max:50', // Add role filter
			'per_page'       => 'sometimes|integer|min:1|max:1000',
		] );

		$payments = $this->paymentService->getPaymentsWithFilters( $filters );
		return $payments;
	}

	/**
	 * Store a newly created payment
	 */
	public function store( Request $request ) {
		$validated = $request->validate( [ 
			'user_id'         => 'required|integer|exists:users,id',
			'date_from'       => 'required|date',
			'date_to'         => 'required|date|after_or_equal:date_from',
			'amount'          => 'required|numeric|min:0',
			'deal_number'     => 'nullable|string|max:255',
			'deal_date'       => 'nullable|date',
			'payment_check'   => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120', // 5MB
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
			'date_from'       => 'sometimes|date',
			'date_to'         => 'sometimes|date|after_or_equal:date_from',
			'amount'          => 'sometimes|numeric|min:0',
			'deal_number'     => 'sometimes|nullable|string|max:255',
			'deal_date'       => 'sometimes|nullable|date',
			'payment_check'   => 'sometimes|nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
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
			'date_from'      => 'sometimes|date',
			'date_to'        => 'sometimes|date',
		] );

		return $this->paymentService->exportPayments( $filters );
	}
}
