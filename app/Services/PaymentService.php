<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\User;
use App\Http\Resources\PaymentResource;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class PaymentService {
	/**
	 * Get payments with filters and pagination
	 */
	public function getPaymentsWithFilters( array $filters = [] ) {
		$query = Payment::with( [ 'user', 'user.role' ] );

		// Apply date range filter for payment period overlap
		if ( isset( $filters['date_from'] ) ) {
			// Payments that end on or after the filter start date
			$query->whereDate( 'date_to', '>=', $filters['date_from'] );
		}
		if ( isset( $filters['date_to'] ) ) {
			// Payments that start on or before the filter end date
			$query->whereDate( 'date_from', '<=', $filters['date_to'] );
		}

		// Apply role filter
		if ( ! empty( $filters['role'] ) ) {
			$query->whereHas('user.role', function ($q) use ($filters) {
				$q->where('name', $filters['role']);
			});
		}

		if ( ! empty( $filters['search'] ) ) {
			$search = $filters['search'];
			$query->where(function ($q) use ($search) {
				$q->where('deal_number', 'like', '%' . $search . '%')
				  ->orWhereHas('user', function ($userQuery) use ($search) {
					  $userQuery->where('name', 'like', '%' . $search . '%')
								->orWhere('email', 'like', '%' . $search . '%');
				  });
			});
		}

		$perPage = $filters['per_page'] ?? 20;

		$payments = $query->orderBy( 'created_at', 'desc' )->paginate( $perPage );
		return PaymentResource::collection($payments);
	}

	/**
	 * Create a new payment
	 */
	public function createPayment( array $data ) {
		return DB::transaction(function () use ($data) {
			// Set deal_date if not provided
			if ( ! isset( $data['deal_date'] ) ) {
				$dealDate = isset( $data['date_from'] ) ? new \DateTime( $data['date_from'] ) : new \DateTime();
				$data['deal_date'] = $dealDate->format( 'Y-m-d' );
			}

			// If amount is not provided, try to get it from the student's room type semester_rate
			if (!isset($data['amount'])) {
				$user = User::with('room.roomType', 'role')->find($data['user_id']);
				if ($user && $user->hasRole('student') && $user->room && $user->room->roomType) {
					$data['amount'] = $user->room->roomType->semester_rate;
				} else {
					// Fallback or throw an error if amount is mandatory and cannot be determined
					$data['amount'] = 0;
				}
			}

			// Handle payment_check file upload
			if ( isset( $data['payment_check'] ) ) {
				$data['payment_check'] = $data['payment_check']->store( 'payment_checks', 'local' );
			}

			$payment = Payment::create( $data );

			return new PaymentResource($payment->load(['user', 'user.role']));
		});
	}

	/**
	 * Get payment details
	 */
	public function getPaymentDetails( $id ) {
		$payment = Payment::with( [ 'user' ] )->findOrFail( $id );
		return new PaymentResource($payment);
	}

	/**
	 * Update payment
	 */
	public function updatePayment( $id, array $data ) {
		return DB::transaction(function () use ($id, $data) {
			$payment = Payment::findOrFail( $id );

			// Handle payment_check file upload
			if ( isset( $data['payment_check'] ) ) {
				// Delete old file if exists
				if ( $payment->payment_check ) {
					Storage::disk( 'local' )->delete( $payment->payment_check );
				}
				$data['payment_check'] = $data['payment_check']->store( 'payment_checks', 'local' );
			}

			$payment->update( $data );

			return new PaymentResource($payment->load(['user', 'user.role']));
		});
	}

	/**
	 * Delete payment
	 */
	public function deletePayment( $id ) {
		$payment = Payment::findOrFail( $id );

		// Delete associated payment_check file
		if ( $payment->payment_check ) {
			Storage::disk( 'public' )->delete( $payment->payment_check );
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

		if ( isset( $filters['date_from'] ) ) {
			$query->whereDate( 'deal_date', '>=', $filters['date_from'] );
		}

		if ( isset( $filters['date_to'] ) ) {
			$query->whereDate( 'deal_date', '<=', $filters['date_to'] );
		}

		$payments = $query->orderBy( 'deal_date', 'desc' )->get();

		// Create CSV content
		$csvContent = "Payment ID,Student Name,Student Email,Deal Number,Deal Date,Amount,Date From,Date To\n";

		foreach ( $payments as $payment ) {
			$dealDate = $payment->deal_date ? (new \DateTime($payment->deal_date))->format('Y-m-d') : '';
			$dateFrom = $payment->date_from ? (new \DateTime($payment->date_from))->format('Y-m-d') : ''; // Assuming date_from is a string or Carbon instance
			$dateTo = $payment->date_to ? (new \DateTime($payment->date_to))->format('Y-m-d') : ''; // Assuming date_to is a string or Carbon instance

			$csvContent .= sprintf(
				"%s,%s,%s,%s,%s,%s,%s,%s\n",
				$payment->id,
				'"' . str_replace( '"', '""', $payment->user->name ?? '' ) . '"',
				($payment->user->email ?? ''), // Null-safe for user->email
				'"' . str_replace( '"', '""', $payment->deal_number ?? '' ) . '"',
				$dealDate,
				$payment->amount,
				$dateFrom,
				$dateTo
			);
		}

		$filename = 'payments_export_' . date( 'Y-m-d_H-i-s' ) . '.csv';

		return response( $csvContent )
			->header( 'Content-Type', 'text/csv' )
			->header( 'Content-Disposition', 'attachment; filename="' . $filename . '"' );
	}
}
