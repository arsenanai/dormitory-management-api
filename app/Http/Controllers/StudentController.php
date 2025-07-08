<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\StudentService;
use Illuminate\Http\Request;

class StudentController extends Controller {
	public function __construct( private StudentService $studentService ) {
	}

	/**
	 * Display a listing of students with filters
	 */
	public function index( Request $request ) {
		$filters = $request->validate( [ 
			'faculty'      => 'sometimes|string',
			'room_id'      => 'sometimes|integer',
			'dormitory_id' => 'sometimes|integer',
			'status'       => 'sometimes|in:pending,active,suspended',
			'per_page'     => 'sometimes|integer|min:1|max:100',
			'page'         => 'sometimes|integer|min:1',
		] );

		return $this->studentService->getStudentsWithFilters( $filters );
	}

	/**
	 * Store a newly created student
	 */
	public function store( Request $request ) {
		$validated = $request->validate( [ 
			'iin'             => 'required|digits:12|unique:users,iin',
			'name'            => 'required|string|max:255',
			'faculty'         => 'required|string|max:255',
			'specialist'      => 'required|string|max:255',
			'enrollment_year' => 'required|integer|digits:4',
			'gender'          => 'required|in:male,female',
			'email'           => 'required|email|max:255|unique:users,email',
			'phone_numbers'   => 'nullable|array',
			'phone_numbers.*' => 'string',
			'room_id'         => 'nullable|exists:rooms,id',
			'password'        => 'required|string|min:6',
			'deal_number'     => 'nullable|string|max:255',
			'city_id'         => 'nullable|integer|exists:cities,id',
			'files'           => 'nullable|array|max:4',
			'files.*'         => 'file|mimes:jpg,jpeg,png,pdf|max:2048',
			'blood_type'      => 'nullable|in:A+,A-,B+,B-,AB+,AB-,O+,O-',
			'violations'      => 'nullable|string|max:1000',
			'parent_name'     => 'nullable|string|max:255',
			'parent_phone'    => 'nullable|string|max:20',
			'mentor_name'     => 'nullable|string|max:255',
			'mentor_email'    => 'nullable|email|max:255',
		] );

		return $this->studentService->createStudent( $validated );
	}

	/**
	 * Display the specified student
	 */
	public function show( $id ) {
		return $this->studentService->getStudentDetails( $id );
	}

	/**
	 * Update the specified student
	 */
	public function update( Request $request, $id ) {
		$validated = $request->validate( [ 
			'name'            => 'sometimes|string|max:255',
			'faculty'         => 'sometimes|string|max:255',
			'specialist'      => 'sometimes|string|max:255',
			'enrollment_year' => 'sometimes|integer|digits:4',
			'gender'          => 'sometimes|in:male,female',
			'email'           => 'sometimes|email|max:255|unique:users,email,' . $id,
			'phone_numbers'   => 'sometimes|array',
			'phone_numbers.*' => 'string',
			'room_id'         => 'sometimes|nullable|exists:rooms,id',
			'deal_number'     => 'sometimes|nullable|string|max:255',
			'city_id'         => 'sometimes|nullable|integer|exists:cities,id',
			'files'           => 'sometimes|array|max:4',
			'files.*'         => 'file|mimes:jpg,jpeg,png,pdf|max:2048',
			'blood_type'      => 'sometimes|nullable|in:A+,A-,B+,B-,AB+,AB-,O+,O-',
			'violations'      => 'sometimes|nullable|string|max:1000',
			'parent_name'     => 'sometimes|nullable|string|max:255',
			'parent_phone'    => 'sometimes|nullable|string|max:20',
			'mentor_name'     => 'sometimes|nullable|string|max:255',
			'mentor_email'    => 'sometimes|nullable|email|max:255',
			'status'          => 'sometimes|in:pending,active,suspended',
		] );

		return $this->studentService->updateStudent( $id, $validated );
	}

	/**
	 * Remove the specified student
	 */
	public function destroy( $id ) {
		$this->studentService->deleteStudent( $id );
		return response()->noContent();
	}

	/**
	 * Approve a student application
	 */
	public function approve( $id ) {
		return $this->studentService->approveStudent( $id );
	}

	/**
	 * Export students to Excel
	 */
	public function export( Request $request ) {
		$filters = $request->validate( [ 
			'faculty'      => 'sometimes|string',
			'room_id'      => 'sometimes|integer',
			'dormitory_id' => 'sometimes|integer',
			'status'       => 'sometimes|in:pending,active,suspended',
		] );

		return $this->studentService->exportStudents( $filters );
	}
}
