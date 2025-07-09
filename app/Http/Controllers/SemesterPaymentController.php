<?php

namespace App\Http\Controllers;

use App\Models\SemesterPayment;
use App\Services\SemesterPaymentService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SemesterPaymentController extends Controller {
	private SemesterPaymentService $semesterPaymentService;

	public function __construct( SemesterPaymentService $semesterPaymentService ) {
		$this->semesterPaymentService = $semesterPaymentService;
	}

	/**
	 * Display a listing of semester payments
	 */
	public function index( Request $request ): JsonResponse {
		$filters = $request->only( [ 
			'semester', 'year', 'semester_type', 'payment_status',
			'dormitory_status', 'user_id'
		] );

		$payments = $this->semesterPaymentService->getAllSemesterPayments( $filters );

		return response()->json( [ 
			'success' => true,
			'data'    => $payments
		] );
	}

	/**
	 * Store a newly created semester payment
	 */
	public function store( Request $request ): JsonResponse {
		$validated = $request->validate( [ 
			'user_id'         => 'required|exists:users,id',
			'semester'        => 'required|string',
			'year'            => 'required|integer',
			'semester_type'   => 'required|in:fall,spring,summer',
			'amount'          => 'required|numeric|min:0',
			'due_date'        => 'required|date',
			'payment_notes'   => 'nullable|string',
			'dormitory_notes' => 'nullable|string',
		] );

		$payment = $this->semesterPaymentService->createSemesterPayment( $validated );

		return response()->json( [ 
			'success' => true,
			'data'    => $payment->load( [ 'user', 'paymentApprover', 'dormitoryApprover' ] )
		], 201 );
	}

	/**
	 * Display the specified semester payment
	 */
	public function show( SemesterPayment $semesterPayment ): JsonResponse {
		return response()->json( [ 
			'success' => true,
			'data'    => $semesterPayment->load( [ 'user', 'paymentApprover', 'dormitoryApprover' ] )
		] );
	}

	/**
	 * Update the specified semester payment
	 */
	public function update( Request $request, SemesterPayment $semesterPayment ): JsonResponse {
		$validated = $request->validate( [ 
			'amount'          => 'sometimes|numeric|min:0',
			'due_date'        => 'sometimes|date',
			'paid_date'       => 'sometimes|date|nullable',
			'payment_notes'   => 'sometimes|string|nullable',
			'dormitory_notes' => 'sometimes|string|nullable',
		] );

		$payment = $this->semesterPaymentService->updateSemesterPayment( $semesterPayment, $validated );

		return response()->json( [ 
			'success' => true,
			'data'    => $payment->load( [ 'user', 'paymentApprover', 'dormitoryApprover' ] )
		] );
	}

	/**
	 * Remove the specified semester payment
	 */
	public function destroy( SemesterPayment $semesterPayment ): JsonResponse {
		$semesterPayment->delete();

		return response()->json( [ 
			'success' => true,
			'message' => 'Semester payment deleted successfully'
		] );
	}

	/**
	 * Approve payment for a semester
	 */
	public function approvePayment( Request $request, SemesterPayment $semesterPayment ): JsonResponse {
		$validated = $request->validate( [ 
			'notes' => 'nullable|string'
		] );

		$payment = $this->semesterPaymentService->approvePayment(
			$semesterPayment,
			$request->user(),
			$validated['notes'] ?? null
		);

		return response()->json( [ 
			'success' => true,
			'message' => 'Payment approved successfully',
			'data'    => $payment->load( [ 'user', 'paymentApprover', 'dormitoryApprover' ] )
		] );
	}

	/**
	 * Reject payment for a semester
	 */
	public function rejectPayment( Request $request, SemesterPayment $semesterPayment ): JsonResponse {
		$validated = $request->validate( [ 
			'notes' => 'nullable|string'
		] );

		$payment = $this->semesterPaymentService->rejectPayment(
			$semesterPayment,
			$request->user(),
			$validated['notes'] ?? null
		);

		return response()->json( [ 
			'success' => true,
			'message' => 'Payment rejected successfully',
			'data'    => $payment->load( [ 'user', 'paymentApprover', 'dormitoryApprover' ] )
		] );
	}

	/**
	 * Approve dormitory access for a semester
	 */
	public function approveDormitoryAccess( Request $request, SemesterPayment $semesterPayment ): JsonResponse {
		$validated = $request->validate( [ 
			'notes' => 'nullable|string'
		] );

		$payment = $this->semesterPaymentService->approveDormitoryAccess(
			$semesterPayment,
			$request->user(),
			$validated['notes'] ?? null
		);

		return response()->json( [ 
			'success' => true,
			'message' => 'Dormitory access approved successfully',
			'data'    => $payment->load( [ 'user', 'paymentApprover', 'dormitoryApprover' ] )
		] );
	}

	/**
	 * Reject dormitory access for a semester
	 */
	public function rejectDormitoryAccess( Request $request, SemesterPayment $semesterPayment ): JsonResponse {
		$validated = $request->validate( [ 
			'notes' => 'nullable|string'
		] );

		$payment = $this->semesterPaymentService->rejectDormitoryAccess(
			$semesterPayment,
			$request->user(),
			$validated['notes'] ?? null
		);

		return response()->json( [ 
			'success' => true,
			'message' => 'Dormitory access rejected successfully',
			'data'    => $payment->load( [ 'user', 'paymentApprover', 'dormitoryApprover' ] )
		] );
	}

	/**
	 * Get semester payment statistics
	 */
	public function getStats( Request $request ): JsonResponse {
		$semester = $request->get( 'semester' );
		$stats = $this->semesterPaymentService->getSemesterPaymentStats( $semester );

		return response()->json( [ 
			'success' => true,
			'data'    => $stats
		] );
	}

	/**
	 * Create semester payments for all students
	 */
	public function createForAllStudents( Request $request ): JsonResponse {
		$validated = $request->validate( [ 
			'semester' => 'required|string',
			'amount'   => 'required|numeric|min:0',
		] );

		$created = $this->semesterPaymentService->createSemesterPaymentsForAllStudents(
			$validated['semester'],
			$validated['amount']
		);

		return response()->json( [ 
			'success' => true,
			'message' => "Created semester payments for {$created} students",
			'data'    => [ 'created_count' => $created ]
		] );
	}

	/**
	 * Get users with dormitory access
	 */
	public function getUsersWithAccess(): JsonResponse {
		$users = $this->semesterPaymentService->getUsersWithDormitoryAccess();

		return response()->json( [ 
			'success' => true,
			'data'    => $users
		] );
	}
}
