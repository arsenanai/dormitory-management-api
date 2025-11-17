<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Collection;

class SemesterPaymentService {
	/**
	 * Get all payments with filtering
	 */
	public function getAllPayments( array $filters = [] ): Collection {
		$query = Payment::with( [ 'user' ] );

		if ( isset( $filters['user_id'] ) ) {
			$query->where( 'user_id', $filters['user_id'] );
		}

		if ( isset( $filters['date_from'] ) ) {
			$query->where( 'date_from', '>=', $filters['date_from'] );
		}

		if ( isset( $filters['date_to'] ) ) {
			$query->where( 'date_to', '<=', $filters['date_to'] );
		}

		return $query->get();
	}

	/**
	 * Create a new payment record
	 */
	public function createPayment( array $data ): Payment {
		return Payment::create( $data );
	}

	/**
	 * Update payment
	 */
	public function updatePayment( Payment $payment, array $data ): Payment {
		$payment->update( $data );
		return $payment->fresh();
	}

}
