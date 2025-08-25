<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\StudentService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

/**
 * Student Management Controller
 * 
 * Handles all student-related operations including:
 * - Student registration and profile management
 * - Student data retrieval with filtering
 * - Student status management
 * - Dormitory assignment and access control
 * 
 * @package App\Http\Controllers
 */
class StudentController extends Controller {
	/**
	 * Student service for business logic operations
	 * 
	 * @var StudentService
	 */
	private StudentService $studentService;

	/**
	 * Constructor with dependency injection
	 * 
	 * @param StudentService $studentService Service for student business logic
	 */
	public function __construct( StudentService $studentService ) {
		$this->studentService = $studentService;
	}

	/**
	 * Display a listing of students with optional filters
	 * 
	 * Supports filtering by:
	 * - Faculty (engineering, medicine, etc.)
	 * - Room assignment
	 * - Dormitory assignment
	 * - Status (pending, active, suspended)
	 * - Pagination
	 * 
	 * @param Request $request HTTP request containing filter parameters
	 * @return JsonResponse JSON response with students data and pagination
	 * 
	 * @throws ValidationException When filter validation fails
	 * 
	 * @example
	 * GET /api/students?faculty=engineering&status=active&per_page=20
	 */
	public function index( Request $request ): JsonResponse {
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
	 * Store a newly created student with comprehensive profile data
	 * 
	 * Creates a new student account with:
	 * - Basic user information (name, email, password)
	 * - Student-specific data (faculty, specialist, enrollment year)
	 * - Contact information (phone numbers)
	 * - Health information (blood type)
	 * - Emergency contacts (parent, mentor)
	 * - Document uploads (files)
	 * 
	 * @param Request $request HTTP request containing student data
	 * @return JsonResponse JSON response with created student data
	 * 
	 * @throws ValidationException When data validation fails
	 * @throws \Exception When student creation fails
	 * 
	 * @example
	 * POST /api/students
	 * {
	 *   "iin": "123456789012",
	 *   "name": "John Doe",
	 *   "email": "john@example.com",
	 *   "faculty": "engineering",
	 *   "specialist": "computer_sciences",
	 *   "enrollment_year": 2024,
	 *   "gender": "male",
	 *   "password": "password"
	 * }
	 */
	public function store( Request $request ): JsonResponse {
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
			'country'         => 'nullable|string|max:255',
			'region'          => 'nullable|string|max:255',
			'city'            => 'nullable|string|max:255',
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
	 * Display the specified student with full profile data
	 * 
	 * Returns comprehensive student information including:
	 * - Basic user data
	 * - Student profile information
	 * - Room and dormitory assignment
	 * - Payment status
	 * - Emergency contacts
	 * 
	 * @param int $id Student ID
	 * @return JsonResponse JSON response with student data
	 * 
	 * @throws \Illuminate\Database\Eloquent\ModelNotFoundException When student not found
	 * 
	 * @example
	 * GET /api/students/123
	 */
	public function show( int $id ): JsonResponse {
		return $this->studentService->getStudentDetails( $id );
	}

	/**
	 * Update the specified student's information
	 * 
	 * Updates student data including:
	 * - Basic information (name, email)
	 * - Academic information (faculty, specialist)
	 * - Contact information
	 * - Health information
	 * - Emergency contacts
	 * - Room assignment
	 * 
	 * @param Request $request HTTP request containing update data
	 * @param int $id Student ID
	 * @return JsonResponse JSON response with updated student data
	 * 
	 * @throws ValidationException When data validation fails
	 * @throws \Illuminate\Database\Eloquent\ModelNotFoundException When student not found
	 * 
	 * @example
	 * PUT /api/students/123
	 * {
	 *   "name": "John Doe Updated",
	 *   "faculty": "medicine",
	 *   "room_id": 5
	 * }
	 */
	public function update( Request $request, int $id ): JsonResponse {
		$validated = $request->validate( [ 
			'name'            => 'sometimes|string|max:255',
			'faculty'         => 'sometimes|string|max:255',
			'specialist'      => 'sometimes|string|max:255',
			'enrollment_year' => 'sometimes|integer|digits:4',
			'gender'          => 'sometimes|in:male,female',
			'email'           => 'sometimes|email|max:255|unique:users,email,' . $id,
			'phone_numbers'   => 'nullable|array',
			'phone_numbers.*' => 'string',
			'room_id'         => 'nullable|exists:rooms,id',
			'deal_number'     => 'nullable|string|max:255',
			'city_id'         => 'nullable|integer|exists:cities,id',
			'country'         => 'nullable|string|max:255',
			'region'          => 'nullable|string|max:255',
			'city'            => 'nullable|string|max:255',
			'blood_type'      => 'nullable|in:A+,A-,B+,B-,AB+,AB-,O+,O-',
			'violations'      => 'nullable|string|max:1000',
			'parent_name'     => 'nullable|string|max:255',
			'parent_phone'    => 'nullable|string|max:20',
			'mentor_name'     => 'nullable|string|max:255',
			'mentor_email'    => 'nullable|email|max:255',
		] );

		return $this->studentService->updateStudent( $id, $validated );
	}

	/**
	 * Remove the specified student from the system
	 * 
	 * Performs soft delete of student account:
	 * - Marks user as deleted (soft delete)
	 * - Removes room assignment
	 * - Maintains data integrity for reporting
	 * 
	 * @param int $id Student ID
	 * @return JsonResponse JSON response with deletion confirmation
	 * 
	 * @throws \Illuminate\Database\Eloquent\ModelNotFoundException When student not found
	 * 
	 * @example
	 * DELETE /api/students/123
	 */
	public function destroy( int $id ): JsonResponse {
		return $this->studentService->deleteStudent( $id );
	}

	/**
	 * Get students by dormitory with filtering options
	 * 
	 * Returns students assigned to a specific dormitory with:
	 * - Student basic information
	 * - Room assignment details
	 * - Payment status
	 * - Access eligibility
	 * 
	 * @param Request $request HTTP request containing dormitory filter
	 * @return JsonResponse JSON response with students in dormitory
	 * 
	 * @throws ValidationException When filter validation fails
	 * 
	 * @example
	 * GET /api/students/by-dormitory?dormitory_id=1&status=active
	 */
	public function getByDormitory( Request $request ): JsonResponse {
		$filters = $request->validate( [ 
			'dormitory_id' => 'required|integer|exists:dormitories,id',
			'status'       => 'sometimes|in:pending,active,suspended',
			'per_page'     => 'sometimes|integer|min:1|max:100',
		] );

		return $this->studentService->getStudentsByDormitory( $filters );
	}

	/**
	 * Get students without dormitory assignment
	 * 
	 * Returns students who are not currently assigned to any dormitory:
	 * - Students pending dormitory assignment
	 * - Students with suspended status
	 * - Students without current semester payment
	 * 
	 * @param Request $request HTTP request containing filter parameters
	 * @return JsonResponse JSON response with unassigned students
	 * 
	 * @throws ValidationException When filter validation fails
	 * 
	 * @example
	 * GET /api/students/unassigned?faculty=engineering
	 */
	public function getUnassigned( Request $request ): JsonResponse {
		$filters = $request->validate( [ 
			'faculty'  => 'sometimes|string',
			'status'   => 'sometimes|in:pending,active,suspended',
			'per_page' => 'sometimes|integer|min:1|max:100',
		] );

		return $this->studentService->getUnassignedStudents( $filters );
	}

	/**
	 * Update student's dormitory access status
	 * 
	 * Manages student's ability to access dormitory based on:
	 * - Payment status
	 * - Violation history
	 * - Administrative decisions
	 * 
	 * @param Request $request HTTP request containing access data
	 * @param int $id Student ID
	 * @return JsonResponse JSON response with updated access status
	 * 
	 * @throws ValidationException When data validation fails
	 * @throws \Illuminate\Database\Eloquent\ModelNotFoundException When student not found
	 * 
	 * @example
	 * PATCH /api/students/123/access
	 * {
	 *   "has_access": true,
	 *   "reason": "Payment completed"
	 * }
	 */
	public function updateAccess( Request $request, int $id ): JsonResponse {
		$validated = $request->validate( [ 
			'has_access' => 'required|boolean',
			'reason'     => 'nullable|string|max:500',
		] );

		return $this->studentService->updateStudentAccess( $id, $validated );
	}

	/**
	 * Get student statistics and analytics
	 * 
	 * Returns comprehensive statistics including:
	 * - Total students by faculty
	 * - Students by dormitory
	 * - Payment status distribution
	 * - Access status summary
	 * 
	 * @param Request $request HTTP request containing filter parameters
	 * @return JsonResponse JSON response with student statistics
	 * 
	 * @throws ValidationException When filter validation fails
	 * 
	 * @example
	 * GET /api/students/statistics?dormitory_id=1
	 */
	public function getStatistics( Request $request ): JsonResponse {
		$filters = $request->validate( [ 
			'dormitory_id' => 'sometimes|integer|exists:dormitories,id',
			'faculty'      => 'sometimes|string',
			'year'         => 'sometimes|integer',
		] );

		return $this->studentService->getStudentStatistics( $filters );
	}

	/**
	 * Approve a student application (activate student)
	 *
	 * PATCH /api/students/{id}/approve
	 *
	 * @param int $id Student user ID
	 * @return JsonResponse
	 */
	public function approve( int $id ): JsonResponse {
		return $this->studentService->approveStudent( $id );
	}
}
