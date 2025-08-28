<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\User;
use App\Http\Resources\PaymentResource;
use Illuminate\Support\Facades\Storage;

class PaymentService {
	/**
	 * Get payments with filters and pagination
	 */
	public function getPaymentsWithFilters( array $filters = [] ) {
		$query = Payment::with( [ 'user' ] );

		// Apply filters
		if ( isset( $filters['user_id'] ) ) {
			$query->where( 'user_id', $filters['user_id'] );
		}

		if ( isset( $filters['status'] ) ) {
			$query->where( 'status', $filters['status'] );
		}

		if ( isset( $filters['payment_method'] ) ) {
			$query->where( 'payment_method', 'like', '%' . $filters['payment_method'] . '%' );
		}

		if ( isset( $filters['date_from'] ) ) {
			$query->whereDate( 'payment_date', '>=', $filters['date_from'] );
		}

		if ( isset( $filters['date_to'] ) ) {
			$query->whereDate( 'payment_date', '<=', $filters['date_to'] );
		}

		$perPage = $filters['per_page'] ?? 20;

		$payments = $query->orderBy( 'paid_date', 'desc' )->paginate( $perPage );

		// Transform the data using PaymentResource
		$transformedData = $payments->through( function ($payment) {
			return new PaymentResource( $payment );
		} );

		return response()->json( $transformedData );
	}

	/**
	 * Create a new payment
	 */
	public function createPayment( array $data ) {
		// Handle receipt file upload
		if ( isset( $data['receipt_file'] ) ) {
			$data['receipt_file'] = $data['receipt_file']->store( 'payment_receipts', 'public' );
		}

		// Set default values for required fields
		$data['payment_status'] = $data['payment_status'] ?? 'pending';
		$data['dormitory_status'] = $data['dormitory_status'] ?? 'pending';
		$data['payment_approved'] = $data['payment_approved'] ?? false;
		$data['dormitory_access_approved'] = $data['dormitory_access_approved'] ?? false;

		// Set due date if not provided (3 months from contract date or today)
		if ( ! isset( $data['due_date'] ) ) {
			$contractDate = isset( $data['contract_date'] ) ? new \DateTime( $data['contract_date'] ) : new \DateTime();
			$data['due_date'] = $contractDate->modify( '+3 months' )->format( 'Y-m-d' );
		}

		$payment = Payment::create( $data );

		return response()->json( $payment->load( 'user' ), 201 );
	}

	/**
	 * Get payment details
	 */
	public function getPaymentDetails( $id ) {
		$payment = Payment::with( [ 'user' ] )->findOrFail( $id );
		return response()->json( $payment );
	}

	/**
	 * Update payment
	 */
	public function updatePayment( $id, array $data ) {
		$payment = Payment::findOrFail( $id );

		// Handle receipt file upload
		if ( isset( $data['receipt_file'] ) ) {
			// Delete old file if exists
			if ( $payment->receipt_file ) {
				Storage::disk( 'public' )->delete( $payment->receipt_file );
			}
			$data['receipt_file'] = $data['receipt_file']->store( 'payment_receipts', 'public' );
		}

		$payment->update( $data );

		return response()->json( $payment->load( 'user' ) );
	}

	/**
	 * Delete payment
	 */
	public function deletePayment( $id ) {
		$payment = Payment::findOrFail( $id );

		// Delete associated receipt file
		if ( $payment->receipt_file ) {
			Storage::disk( 'public' )->delete( $payment->receipt_file );
		}

		$payment->delete();
		return response()->json( [ 'message' => 'Payment deleted successfully' ], 200 );
	}

	/**
	 * Export payments to CSV
	 */
	public function exportPayments( array $filters = [] ) {
		$query = Payment::with( [ 'user' ] );

		// Apply same filters as getPaymentsWithFilters
		if ( isset( $filters['user_id'] ) ) {
			$query->where( 'user_id', $filters['user_id'] );
		}

		if ( isset( $filters['status'] ) ) {
			$query->where( 'status', $filters['status'] );
		}

		if ( isset( $filters['payment_method'] ) ) {
			$query->where( 'payment_method', 'like', '%' . $filters['payment_method'] . '%' );
		}

		if ( isset( $filters['date_from'] ) ) {
			$query->whereDate( 'payment_date', '>=', $filters['date_from'] );
		}

		if ( isset( $filters['date_to'] ) ) {
			$query->whereDate( 'payment_date', '<=', $filters['date_to'] );
		}

		$payments = $query->orderBy( 'payment_date', 'desc' )->get();

		// Create CSV content
		$csvContent = "Payment ID,Student Name,Student Email,Contract Number,Contract Date,Payment Date,Amount,Payment Method,Status\n";

		foreach ( $payments as $payment ) {
			$contractDate = $payment->contract_date ? $payment->contract_date->format( 'Y-m-d' ) : '';
			$paymentDate = $payment->payment_date ? $payment->payment_date->format( 'Y-m-d' ) : '';

			$csvContent .= sprintf(
				"%s,%s,%s,%s,%s,%s,%s,%s,%s\n",
				$payment->id,
				'"' . str_replace( '"', '""', $payment->user->name ?? $payment->name ) . '"',
				$payment->user->email ?? '',
				'"' . str_replace( '"', '""', $payment->contract_number ?? '' ) . '"',
				$contractDate,
				$paymentDate,
				$payment->amount,
				'"' . str_replace( '"', '""', $payment->payment_method ?? '' ) . '"',
				$payment->status
			);
		}

		$filename = 'payments_export_' . date( 'Y-m-d_H-i-s' ) . '.csv';

		return response( $csvContent )
			->header( 'Content-Type', 'text/csv' )
			->header( 'Content-Disposition', 'attachment; filename="' . $filename . '"' );
	}
}
