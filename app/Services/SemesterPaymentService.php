<?php

namespace App\Services;

use App\Models\SemesterPayment;
use App\Models\User;
use Illuminate\Support\Collection;

class SemesterPaymentService {
	/**
	 * Get all semester payments with filtering
	 */
	public function getAllSemesterPayments( array $filters = [] ): Collection {
		$query = SemesterPayment::with( [ 'user', 'paymentApprover', 'dormitoryApprover' ] );

		if ( isset( $filters['semester'] ) ) {
			$query->where( 'semester', $filters['semester'] );
		}

		if ( isset( $filters['year'] ) ) {
			$query->where( 'year', $filters['year'] );
		}

		if ( isset( $filters['semester_type'] ) ) {
			$query->where( 'semester_type', $filters['semester_type'] );
		}

		if ( isset( $filters['payment_status'] ) ) {
			$query->where( 'payment_status', $filters['payment_status'] );
		}

		if ( isset( $filters['dormitory_status'] ) ) {
			$query->where( 'dormitory_status', $filters['dormitory_status'] );
		}

		if ( isset( $filters['user_id'] ) ) {
			$query->where( 'user_id', $filters['user_id'] );
		}

		return $query->get();
	}

	/**
	 * Create a new semester payment record
	 */
	public function createSemesterPayment( array $data ): SemesterPayment {
		return SemesterPayment::create( $data );
	}

	/**
	 * Update semester payment
	 */
	public function updateSemesterPayment( SemesterPayment $payment, array $data ): SemesterPayment {
		$payment->update( $data );
		return $payment->fresh();
	}

	/**
	 * Approve payment for a semester
	 */
	public function approvePayment( SemesterPayment $payment, User $approver, string $notes = null ): SemesterPayment {
		$payment->update( [ 
			'payment_approved'    => true,
			'payment_approved_at' => now(),
			'payment_approved_by' => $approver->id,
			'payment_status'      => 'approved',
			'payment_notes'       => $notes,
		] );

		return $payment->fresh();
	}

	/**
	 * Reject payment for a semester
	 */
	public function rejectPayment( SemesterPayment $payment, User $approver, string $notes = null ): SemesterPayment {
		$payment->update( [ 
			'payment_approved'    => false,
			'payment_approved_at' => now(),
			'payment_approved_by' => $approver->id,
			'payment_status'      => 'rejected',
			'payment_notes'       => $notes,
		] );

		return $payment->fresh();
	}

	/**
	 * Approve dormitory access for a semester
	 */
	public function approveDormitoryAccess( SemesterPayment $payment, User $approver, string $notes = null ): SemesterPayment {
		$payment->update( [ 
			'dormitory_access_approved' => true,
			'dormitory_approved_at'     => now(),
			'dormitory_approved_by'     => $approver->id,
			'dormitory_status'          => 'approved',
			'dormitory_notes'           => $notes,
		] );

		return $payment->fresh();
	}

	/**
	 * Reject dormitory access for a semester
	 */
	public function rejectDormitoryAccess( SemesterPayment $payment, User $approver, string $notes = null ): SemesterPayment {
		$payment->update( [ 
			'dormitory_access_approved' => false,
			'dormitory_approved_at'     => now(),
			'dormitory_approved_by'     => $approver->id,
			'dormitory_status'          => 'rejected',
			'dormitory_notes'           => $notes,
		] );

		return $payment->fresh();
	}

	/**
	 * Get current semester payment for a user
	 */
	public function getCurrentSemesterPayment( User $user ): ?SemesterPayment {
		return $user->semesterPayments()
			->where( 'semester', SemesterPayment::getCurrentSemester() )
			->first();
	}

	/**
	 * Get users who can access dormitory in current semester
	 */
	public function getUsersWithDormitoryAccess(): Collection {
		return User::whereHas( 'semesterPayments', function ($query) {
			$query->currentSemester()
				->where( 'payment_approved', true )
				->where( 'dormitory_access_approved', true );
		} )->get();
	}

	/**
	 * Get statistics for semester payments
	 */
	public function getSemesterPaymentStats( string $semester = null ): array {
		$semester = $semester ?? SemesterPayment::getCurrentSemester();

		$payments = SemesterPayment::where( 'semester', $semester )->get();

		return [ 
			'total_payments'     => $payments->count(),
			'payment_approved'   => $payments->where( 'payment_approved', true )->count(),
			'dormitory_approved' => $payments->where( 'dormitory_access_approved', true )->count(),
			'both_approved'      => $payments->where( 'payment_approved', true )
				->where( 'dormitory_access_approved', true )
				->count(),
			'pending_payment'    => $payments->where( 'payment_status', 'pending' )->count(),
			'pending_dormitory'  => $payments->where( 'dormitory_status', 'pending' )->count(),
			'total_amount'       => $payments->sum( 'amount' ),
			'approved_amount'    => $payments->where( 'payment_approved', true )->sum( 'amount' ),
		];
	}

	/**
	 * Create semester payment records for all students
	 */
	public function createSemesterPaymentsForAllStudents( string $semester, float $amount ): int {
		$students = User::whereHas( 'role', function ($query) {
			$query->where( 'name', 'student' );
		} )->get();

		$created = 0;
		foreach ( $students as $student ) {
			$existing = SemesterPayment::where( 'user_id', $student->id )
				->where( 'semester', $semester )
				->exists();

			if ( ! $existing ) {
				$this->createSemesterPayment( [ 
					'user_id'       => $student->id,
					'semester'      => $semester,
					'year'          => (int) explode( '-', $semester )[0],
					'semester_type' => explode( '-', $semester )[1],
					'amount'        => $amount,
					'due_date'      => now()->addMonths( 2 ),
				] );
				$created++;
			}
		}

		return $created;
	}
}
