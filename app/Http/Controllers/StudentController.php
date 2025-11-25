<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\StudentService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\StudentResource;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\UploadedFile;

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
	 * Constructor with dependency injection
	 * 
	 * @param StudentService $studentService Service for student business logic
	 */
	public function __construct( private StudentService $studentService ) {}

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
			'search'       => 'sometimes|string|max:255',
			'faculty'      => 'sometimes|string',
			'room_id'      => 'sometimes|integer',
			'dormitory_id' => 'sometimes|integer',
			'status'       => 'sometimes|in:pending,active,suspended',
			'per_page'     => 'sometimes|integer|min:1|max:1000',
			'page'         => 'sometimes|integer|min:1',
			'fields'       => 'sometimes|string', // Comma-separated list of fields to select
		] );

		return response()->json($this->studentService->getStudentsWithFilters( $filters, Auth::user()->load('adminDormitory') ));
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
		if( isset( $request->student_profile ) 
			&& isset( $request->student_profile['files'] ) 
		    && is_array( $request->student_profile['files'] ) ) {
			foreach($request->student_profile['files'] as $index => $file) {
				if( isset( $file ) /* && $file instanceof UploadedFile*/ ) {
					Log::info('Debugging student file ' . $index, [
						'original_name' => $file->getClientOriginalName(),
						'extension' => $file->getClientOriginalExtension(),
						'mime_type' => $file->getMimeType(),
						'size' => $file->getSize(),
						'is_valid' => $file->isValid(),
					]);
				}
			}
		}
		
		// Manually construct the 'name' field from first_name and last_name before validation.
		if ($request->has('first_name') && $request->has('last_name')) {
			$request->merge(['name' => trim($request->input('first_name') . ' ' . $request->input('last_name'))]);
		}

		$validated = $request->validate( [ 
			'bed_id'                                         => 'nullable|integer|exists:beds,id',
			'email'                                          => 'required|email|max:255|unique:users,email',
			'first_name'                                     => 'required|string|max:255',
			'last_name'				                         => 'required|string|max:255',
			'name'                                           => 'required|string|max:255',
			'password'                                       => 'required|string|min:6|confirmed',
			'phone_numbers.*'                                => 'string',
			'phone_numbers'                                  => 'nullable|array',
			'room_id'                                        => 'nullable|integer|exists:rooms,id',
			'student_profile.agree_to_dormitory_rules'       => 'required|boolean',
			'student_profile.allergies'                      => 'nullable|string|max:1000',
			'student_profile.blood_type'                     => ['nullable', 'string'],
			'student_profile.city'                           => 'nullable|string|max:255',
			'student_profile.country'                        => 'nullable|string|max:255',
			'student_profile.deal_number'                    => 'nullable|string|max:255',
			'student_profile.emergency_contact_name'         => 'nullable|string|max:255',
			'student_profile.emergency_contact_phone'        => 'nullable|string|max:255',
			'student_profile.emergency_contact_relationship' => 'nullable|string|max:255',
			'student_profile.enrollment_year'                => 'required|integer|digits:4',
			'student_profile.faculty'                        => 'required|string|max:255',
			'student_profile.files.*'                        => 'nullable|mimetypes:image/jpg,image/jpeg,image/png,application/pdf,application/octet-stream|max:2048',
			'student_profile.files'                          => 'nullable|array|max:3',
			'student_profile.gender'                         => 'required|in:male,female',
			'student_profile.has_meal_plan'                  => 'required|boolean',
			'student_profile.iin'                            => 'required|digits:12|unique:student_profiles,iin',
			'student_profile.mentor_email'                   => 'nullable|email|max:255',
			'student_profile.mentor_name'                    => 'nullable|string|max:255',
			'student_profile.parent_email'                   => 'nullable|email|max:255',
			'student_profile.parent_name'                    => 'nullable|string|max:255',
			'student_profile.parent_phone'                   => 'nullable|string|max:20',
			'student_profile.region'                         => 'nullable|string|max:255',
			'student_profile.specialist'                     => 'required|string|max:255',
			'student_profile.violations'                     => 'nullable|string|max:1000',
			// 'payment.payment_check'						     => 'required|mimetypes:image/jpg,image/jpeg,image/png,application/pdf,application/octet-stream|max:2048',
		] );

		$student = $this->studentService->createStudent( $validated, Auth::user()->adminDormitory );
		return response()->json(
			$student->load(['studentProfile', 'role', 'room.dormitory', 'studentBed']),
			201
		);
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
		return response()->json($this->studentService->getStudentDetails( $id ));
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
	 *   "first_name": "John Updated",
	 *   "faculty": "medicine",
	 *   "room_id": 5
	 * }
	 */
	public function update( Request $request, int $id ): JsonResponse {
		if( isset( $request->student_profile ) 
			&& isset( $request->student_profile['files'] ) 
		    && is_array( $request->student_profile['files'] ) ) {
			foreach($request->student_profile['files'] as $index => $file) {
				if( isset( $file ) && method_exists('getClientOriginalName', $file) ) {
					Log::info('Debugging student file ' . $index, [
						'original_name' => $file->getClientOriginalName(),
						'extension' => $file->getClientOriginalExtension(),
						'mime_type' => $file->getMimeType(),
						'size' => $file->getSize(),
						'is_valid' => $file->isValid(),
					]);
				}
			}
		}

		$validated = $request->validate( [ 
			'bed_id'                                         => 'nullable|exists:beds,id',
			'email'                                          => 'required|email|max:255',
			'first_name'                                     => 'sometimes|string|max:255',
			'last_name'				                         => 'sometimes|string|max:255',
			'name'                                           => 'sometimes|string|max:255',
			'password'                                       => 'nullable|string|min:6|confirmed',
			'phone_numbers.*'                                => 'string',
			'phone_numbers'                                  => 'nullable|array',
			'room_id'                                        => 'nullable|exists:rooms,id',
			'status'                                         => 'nullable|in:pending,active,suspended',
			'student_profile.agree_to_dormitory_rules'       => 'nullable|boolean',
			'student_profile.allergies'                      => 'nullable|string|max:1000',
			'student_profile.blood_type'                     => ['nullable', 'string'],
			'student_profile.city'                           => 'nullable|string|max:255',
			'student_profile.country'                        => 'nullable|string|max:255',
			'student_profile.deal_number'                    => 'nullable|string|max:255',
			'student_profile.emergency_contact_name'         => 'nullable|string|max:255',
			'student_profile.emergency_contact_phone'        => 'nullable|string|max:255',
			'student_profile.emergency_contact_relationship' => 'nullable|string|max:255',
			'student_profile.enrollment_year'                => 'nullable|integer|digits:4',
			'student_profile.faculty'                        => 'nullable|string|max:255',
			'student_profile.files.*'                        => [
				'nullable',
				$this->validateUploadedFile(...),
			],
			'student_profile.files'                          => 'nullable|array|max:4',
			'student_profile.gender'                         => 'nullable|in:male,female',
			'student_profile.has_meal_plan'                  => 'nullable|boolean',
			'student_profile.iin'                            => 'sometimes|digits:12',
			'student_profile.mentor_email'                   => 'nullable|email|max:255',
			'student_profile.mentor_name'                    => 'nullable|string|max:255',
			'student_profile.parent_email'                   => 'nullable|email|max:255',
			'student_profile.parent_name'                    => 'nullable|string|max:255',
			'student_profile.parent_phone'                   => 'nullable|string|max:20',
			'student_profile.region'                         => 'nullable|string|max:255',
			'student_profile.specialist'                     => 'nullable|string|max:255', 
			'student_profile.student_id'                     => 'nullable|string|max:255',
			'student_profile.violations'                     => 'nullable|string|max:1000',
			// 'payment.payment_check'						     => [
			// 	'nullable',
			// 	$this->validateUploadedFile(...),
			// ],
		] );

		$student = $this->studentService->updateStudent( $id, $validated, Auth::user()->load('adminDormitory') );
		return response()->json($student);
	}

	private function validateUploadedFile( $attribute, $value, $fail ): void {
		if ( $value instanceof UploadedFile ) {
			$validator = \Illuminate\Support\Facades\Validator::make(
				[ 'file' => $value ],
				[ 'file' => 'mimes:jpeg,jpg,png,pdf|mimetypes:image/jpg,image/jpeg,image/png,application/pdf,application/octet-stream|max:2048' ]
			);
			if ( $validator->fails() ) {
				$fail( $validator->errors()->first( 'file' ) );
			}
		}
	}

	/**
	 * Export students to a CSV file based on filters.
	 *
	 * @param Request $request
	 * @return \Illuminate\Http\Response
	 */
	public function export(Request $request): \Illuminate\Http\Response
	{
		$filters = $request->all();
		$user = Auth::user()->load('adminDormitory');

		return $this->studentService->exportStudents($filters, $user);
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
		$this->studentService->deleteStudent( $id );
		return response()->json(['message' => 'Student deleted successfully'], 200);
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

		return response()->json($this->studentService->getStudentsByDormitory( $filters, Auth::user()->load('adminDormitory') ));
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

		return response()->json($this->studentService->getUnassignedStudents( $filters ));
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

		$student = $this->studentService->updateStudentAccess( $id, $validated );
		return response()->json([
			'message' => 'Student access updated successfully',
			'student' => $student
		]);
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

		return response()->json($this->studentService->getStudentStatistics( $filters, Auth::user() ));
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
		$student = $this->studentService->approveStudent( $id );
		return response()->json([
			'message' => 'Student approved successfully',
			'student' => $student
		]);
	}

	public function listAll(): JsonResponse {
		$students = $this->studentService->listAllStudents();
		return response()->json($students);
	}
}
