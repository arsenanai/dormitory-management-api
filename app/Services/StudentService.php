<?php

namespace App\Services;

use App\Models\Role;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use App\Models\StudentProfile;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;
use App\Models\Bed;
use App\Models\Dormitory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class StudentService {

	private $paymentService;

	public function __construct() {
		$this->paymentService = new PaymentService();
	}
	/**
	 * Get students with filters and pagination
	 */
	public function getStudentsWithFilters( array $filters = [], User $authUser ) {
		return $this->buildStudentQuery( $filters, $authUser, true );
	}

	/**
	 * Create a new student
	 */
	public function updateStudent( $id, array $data, User $authUser ) {
		return DB::transaction( function () use ($id, $data, $authUser) {
			$student = User::whereHas( 'role', fn( $q ) => $q->where( 'name', 'student' ) )
				->with( [ 'studentBed', 'studentProfile' ] ) // Eager load relationships for efficiency
				->findOrFail( $id );

			// If a new bed/room is being assigned, validate gender compatibility with the new dormitory.
			if ( isset( $data['bed_id'] ) ) {
				$newBed = Bed::with( 'room.dormitory' )->find( $data['bed_id'] );
				if ( $newBed && $newBed->room ) {
					$newDormitory = $newBed->room->dormitory;
					$studentGender = $data['gender'] ?? $student->studentProfile->gender;
					if (
						( $newDormitory->gender === 'male' && $studentGender === 'female' ) ||
						( $newDormitory->gender === 'female' && $studentGender === 'male' )
					) {
						throw ValidationException::withMessages( [ 'bed_id' => 'The selected dormitory does not accept students of this gender.' ] );
					}
				}
			}

			// Handle bed assignment and get the new room_id if it changes
			$newRoomId = $this->processBedAssignment( $student, $data['bed_id'] ?? null, $authUser->hasRole( 'sudo' ) );
			if ( $newRoomId !== false ) { // `false` indicates no change
				$data['room_id'] = $newRoomId;
			}

			// Prepare data for User and StudentProfile models
			$userData = $this->prepareUserData( $data, $student );
			$dormitoryId = $authUser->adminDormitory->id ?? $student->dormitory_id;
			if ( $dormitoryId ) {
				$userData['dormitory_id'] = $dormitoryId;
			}
			$profileData = $this->prepareProfileData( $data, true );

			// Only process file uploads if new files are included in the student_profile.
			if ( isset( $data['student_profile']['files'] ) ) {
				Log::info( 'StudentService: Processing file uploads.', $data['student_profile']['files'] );
				// Process files and get the final array of paths. This must be done
				// before the main profile update to ensure the correct paths are saved.
				$processedFilePaths = $this->processFileUploads( $data['student_profile'], $student->studentProfile );
				// Explicitly set the processed files array on the profile data.
				$profileData['files'] = $processedFilePaths;
			}

			// Update the User model with user-specific data.
			if ( ! empty( $userData ) ) {
				$student->update( $userData );
			}

			// Update the StudentProfile model with profile-specific data.
			if ( ! empty( $profileData ) && $student->studentProfile ) {
				// Ensure student_id is not accidentally nulled out on update
				if ( ! isset( $profileData['student_id'] ) ) {
					$profileData['student_id'] = $student->studentProfile->student_id;
				}
				$student->studentProfile->update( $profileData );
			}

			// Return the fresh, fully-loaded student model.
			return $student->fresh()->load( [ 'role', 'studentProfile', 'room.dormitory', 'studentBed' ] );
		} );
	}

	/**
	 * Get student details
	 */
	public function getStudentDetails( $id ) {
		$student = User::whereHas( 'role', fn( $q ) => $q->where( 'name', 'student' ) )
			->with( [
				'role',
				'studentProfile',
				'room.beds',
				'room.dormitory',
				'room.roomType',
				'studentBed',
				'payments' => function ( $q ) {
					$q->orderBy( 'created_at', 'desc' );
				}
			] )
			->findOrFail( $id );

		return $student; // Return User model
	}

	/**
	 * Delete student
	 */
	public function deleteStudent( $id ) {
		$student = User::whereHas( 'role', fn( $q ) => $q->where( 'name', 'student' ) )
			->with( 'studentProfile', 'studentBed' ) // Eager load for cleanup
			->findOrFail( $id );

		// Delete associated files from StudentProfile
		if ( $student->studentProfile && ! empty( $student->studentProfile->files ) ) {
			$filesToDelete = $student->studentProfile->files;
			// If files are stored as a JSON string, decode it first.
			if ( is_string( $filesToDelete ) ) {
				$filesToDelete = json_decode( $filesToDelete, true ) ?? [];
			}
			$this->deleteFiles( is_array( $filesToDelete ) ? $filesToDelete : [] );
		}

		// Free up the bed if assigned
		if ( $student->studentBed ) {
			$student->studentBed->user_id = null;
			$student->studentBed->is_occupied = false;
			$student->studentBed->save();
		}

		$student->studentProfile->delete();
		$student->delete();
		return true; // Return boolean for success
	}

	/**
	 * Approve student application
	 */
	public function approveStudent( $id ) {
		$student = User::whereHas( 'role', fn( $q ) => $q->where( 'name', 'student' ) )
			->findOrFail( $id );

		$student->status = 'active';
		$student->save();

		return $student->load( [ 'role', 'studentProfile', 'room' ] ); // Return User model
	}

	/**
	 * Export students to CSV
	 */
	public function exportStudents( array $filters = [], User $authUser ) {
		// Use the same query logic as getStudentsWithFilters for consistency, but don't paginate
		$query = $this->buildStudentQuery( $filters, $authUser, false );

		$students = $query->get();

		// Define which columns to export based on the request, defaulting to table columns
		$defaultCols = [ 'name', 'status', 'enrollment_year', 'faculty', 'dormitory', 'bed', 'phone' ];
		$exportCols = isset( $filters['columns'] ) ? explode( ',', $filters['columns'] ) : $defaultCols;

		$headers = array_map( 'ucfirst', $exportCols );
		$csvContent = implode( ',', $headers ) . "\n";

		foreach ( $students as $student ) {
			$rowData = [];
			foreach ( $exportCols as $col ) {
				$value = '';
				switch ( $col ) {
					case 'name':
						$value = $student->name;
						break;
					case 'status':
						$value = $student->status;
						break;
					case 'enrollment_year':
						$value = $student->studentProfile->enrollment_year ?? '';
						break;
					case 'faculty':
						$value = $student->studentProfile->faculty ?? '';
						break;
					case 'dormitory':
						$value = $student->room->dormitory->name ?? '';
						break;
					case 'bed':
						$value = $student->room ? ( $student->room->number . '-' . ( $student->studentBed->bed_number ?? '' ) ) : '';
						break;
					case 'phone':
						$value = is_array( $student->phone_numbers ) ? implode( ';', $student->phone_numbers ) : ( $student->phone ?? '' );
						break;
					// Add other cases for any other potential columns
				}
				// Escape quotes for CSV
				$rowData[] = '"' . str_replace( '"', '""', $value ) . '"';
			}
			$csvContent .= implode( ',', $rowData ) . "\n";
		}

		$filename = 'students_export_' . date( 'Y-m-d_H-i-s' ) . '.csv';

		return response( $csvContent )
			->header( 'Content-Type', 'text/csv' )
			->header( 'Content-Disposition', 'attachment; filename="' . $filename . '"' );
	}

	private function buildStudentQuery( array $filters = [], User $authUser, bool $paginate = true ) {
		$query = User::whereHas( 'role', fn( $q ) => $q->where( 'name', 'student' ) )->with( [ 'role', 'studentProfile', 'room.dormitory', 'studentBed' ] );

		// Filter by the admin's dormitory if the user is an admin or if the flag is set
		if (
			( $filters['my_dormitory_only'] ?? false ) ||
			( $authUser->hasRole( 'admin' ) && ! $authUser->hasRole( 'sudo' ) && $authUser->adminDormitory )
		) {
			$query->where( 'dormitory_id', $authUser->adminDormitory->id );
		}

		// Apply other filters
		if ( isset( $filters['faculty'] ) ) {
			$query->whereHas( 'studentProfile', function ( $q ) use ( $filters ) {
				$q->where( 'faculty', 'like', '%' . $filters['faculty'] . '%' );
			} );
		}

		if ( isset( $filters['room_id'] ) ) {
			$query->where( 'room_id', $filters['room_id'] );
		}

		if ( isset( $filters['status'] ) ) {
			$query->where( 'status', $filters['status'] );
		}

		// Apply search query across multiple fields
		if ( isset( $filters['search'] ) ) {
			$search = $filters['search'];
			$query->where( function ( $q ) use ( $search ) {
				$q->where( 'name', 'like', "%{$search}%" )
					->orWhere( 'email', 'like', "%{$search}%" )
					->orWhereHas( 'studentProfile', function ( $sq ) use ( $search ) {
						$sq->where( 'faculty', 'like', "%{$search}%" )
							->orWhere( 'iin', 'like', "%{$search}%" );
					} )
					->orWhereHas( 'room', function ( $rq ) use ( $search ) {
						$rq->where( 'number', 'like', "%{$search}%" );
					} );
			} );
		}

		if ( $paginate ) {
			$perPage = $filters['per_page'] ?? 20;
			return $query->paginate( $perPage );
		}

		return $query;
	}

	/**
	 * Get students with filters and pagination
	 */
	public function createStudent( array $data, Dormitory $dormitory ) {
		\Log::info( 'StudentService: createStudent method started.', $data );

		return DB::transaction( function () use ($data, $dormitory) {
			// Validate that the student's gender is compatible with the dormitory's gender policy.
			$gender = $data['gender'] ?? null;
			if (
				( $dormitory->gender === 'male' && $gender === 'female' ) ||
				( $dormitory->gender === 'female' && $gender === 'male' )
			) {
				throw ValidationException::withMessages( [ 'room_id' => 'The selected dormitory does not accept students of this gender.' ] );
			}

			// Prepare data for User and StudentProfile models
			$userData = $this->prepareUserData( $data );
			$profileData = $this->prepareProfileData( $data, false );

			// The 'files' array is already validated and part of $data['student_profile'].
			// We just need to store them and get their paths.
			if ( isset( $profileData['files'] ) && is_array( $profileData['files'] ) ) {
				$storedFilePaths = $this->storeNewFiles( $profileData['files'] );
				$profileData['files'] = $storedFilePaths;
			}
			// If a bed is assigned, update the user's room_id and dormitory_id
			// if (isset($data['bed_id'])) {
			// 	$bed = Bed::with('room')->find($data['bed_id']);
			// 	if ($bed) {
			// 		$userData['room_id'] = $bed->room_id;
			// 		$userData['dormitory_id'] = $bed->room->dormitory_id;
			// 	}
			// } else {
			$userData['dormitory_id'] = $dormitory->id;
			// }
			// Create the User
			$student = User::create( $userData );

			// Create the StudentProfile
			$profileData['user_id'] = $student->id;
			StudentProfile::create( $profileData );

			// Assign bed if provided
			$this->processBedAssignment( $student, $data['bed_id'] ?? null, false );

			$room = Room::with( 'roomType' )->findOrFail( $userData['room_id'] );

			if ( request()->hasFile( 'payment.payment_check' ) ) {
				$file = request()->file( 'payment.payment_check' );

				$this->paymentService->createPayment( [
					'user_id'       => $student->id,
					'amount'        => $this->calculateSemesterFee( $room->roomType ),
					'date_from'     => $this->getSemesterStartDate(),
					'date_to'       => $this->getSemesterEndDate(),
					'deal_number'   => $profileData['deal_number'] ?? null,
					'deal_date'     => now(),
					'payment_check' => $file,
				] );
			}

			return $student->load( [ 'role', 'studentProfile', 'room.dormitory', 'studentBed' ] );
		} );
	}

	private function calculateSemesterFee( RoomType $roomType ): float {
		// room type has semester_rate, we need to return that amount
		return $roomType->semester_rate;
	}

	private function getSemesterStartDate(): Carbon {
		// typically 01.01.[current_year] or 01.09.[current_year] depending on semester
		$month = now()->month;
		$year = now()->year;
		if ( $month >= 1 && $month <= 6 ) {
			return Carbon::create( $year, 1, 1 );
		} else {
			return Carbon::create( $year, 9, 1 );
		}
	}

	private function getSemesterEndDate(): Carbon {
		// the last day of the may or december depending on year
		$month = now()->month;
		$year = now()->year;
		if ( $month >= 1 && $month <= 6 ) {
			return Carbon::create( $year, 6, 30 );
		} else {
			return Carbon::create( $year, 12, 31 );
		}
	}

	/**
	 * Get students by dormitory
	 */
	public function getStudentsByDormitory( array $filters = [], User $authUser ) {
		$query = User::whereHas( 'role', fn( $q ) => $q->where( 'name', 'student' ) )
			->with( [ 'role', 'studentProfile', 'room', 'room.dormitory' ] );
		if ( $authUser && optional( $authUser->role )->name === 'admin' && ! isset( $filters['dormitory_id'] ) ) {
			// Get dormitory_id from AdminProfile relationship
			$adminDormitoryId = $authUser->adminProfile?->dormitory_id;
			if ( $adminDormitoryId ) {
				$filters['dormitory_id'] = (int) $adminDormitoryId;
			}
		}

		// Apply filters
		if ( isset( $filters['dormitory_id'] ) ) {
			$query->whereHas( 'room', function ( $q ) use ( $filters ) {
				$q->where( 'dormitory_id', $filters['dormitory_id'] );
			} );
		}

		if ( isset( $filters['status'] ) ) {
			$query->where( 'status', $filters['status'] );
		}

		$perPage = $filters['per_page'] ?? 20;

		$students = $query->paginate( $perPage );

		return $students; // Return Paginator instance
	}

	/**
	 * Get unassigned students
	 */
	public function getUnassignedStudents( array $filters = [] ) {
		$query = User::whereHas( 'role', fn( $q ) => $q->where( 'name', 'student' ) )
			->whereNull( 'room_id' )
			->with( [ 'role', 'studentProfile' ] );

		// Apply filters
		if ( isset( $filters['faculty'] ) ) {
			$query->whereHas( 'studentProfile', function ( $q ) use ( $filters ) {
				$q->where( 'faculty', 'like', '%' . $filters['faculty'] . '%' );
			} );
		}

		if ( isset( $filters['status'] ) ) {
			$query->where( 'status', $filters['status'] );
		}

		$perPage = $filters['per_page'] ?? 20;

		$students = $query->paginate( $perPage );

		return $students; // Return Paginator instance
	}

	/**
	 * Update student access
	 */
	public function updateStudentAccess( $id, array $data ) {
		$student = User::whereHas( 'role', fn( $q ) => $q->where( 'name', 'student' ) )
			->findOrFail( $id );

		if ( isset( $data['has_access'] ) ) {
			$student->status = $data['has_access'] ? 'active' : 'suspended';
		}
		$student->save();


		return $student->load( [ 'role', 'room', 'studentProfile' ] ); // Return User model
	}

	/**
	 * Get student statistics
	 */
	public function getStudentStatistics( array $filters = [], User $authUser ) {
		$query = User::whereHas( 'role', fn( $q ) => $q->where( 'name', 'student' ) );

		// Apply filters first, then default to admin's dormitory if no filter is set
		if ( isset( $filters['dormitory_id'] ) ) {
			$query->where( 'dormitory_id', $filters['dormitory_id'] );
		} elseif ( $authUser->hasRole( 'admin' ) && $authUser->adminDormitory ) {
			$query->where( 'dormitory_id', $authUser->adminDormitory->id );
		}

		if ( isset( $filters['faculty'] ) ) {
			$query->whereHas( 'studentProfile', function ( $q ) use ( $filters ) {
				$q->where( 'faculty', 'like', '%' . $filters['faculty'] . '%' );
			} );
		}

		$stats = [
			'total'     => $query->count(),
			'active'    => ( clone $query )->where( 'status', 'active' )->count(),
			'pending'   => ( clone $query )->where( 'status', 'pending' )->count(),
			'suspended' => ( clone $query )->where( 'status', 'suspended' )->count(),
		];

		return $stats; // Return array
	}

	/**
	 * Delete files from storage
	 */
	private function deleteFiles( array $files ) {
		// Delete up to 3 files (the last one is for payments)
		foreach ( $files as $file ) {
			// Ensure the file path is a valid, non-empty string and exists before attempting to delete.
			if ( is_string( $file ) && ! empty( $file ) && Storage::disk( 'local' )->exists( $file ) ) {
				Storage::disk( 'local' )->delete( $file );
			}
		}
	}

	/**
	 * Handles the logic for assigning, re-assigning, or un-assigning a bed for a student.
	 *
	 * @param User $student The student user instance.
	 * @param int|null $newBedId The ID of the new bed, or null to un-assign.
	 * @return int|null|false Returns the new room_id on change, null if unassigned, or false if no change occurred.
	 * @param bool $isSudo
	 * @throws ValidationException
	 */
	private function processBedAssignment( User $student, ?int $newBedId, bool $isSudo = false ) {
		$oldBed = $student->studentBed;
		$oldBedId = $oldBed->id ?? null;

		if ( $newBedId === $oldBedId ) {
			return false; // No change in bed assignment.
		}

		// Free up the old bed if it exists.
		if ( $oldBed ) {
			$oldBed->user_id = null;
			$oldBed->is_occupied = false;
			$oldBed->save();
		}

		// If a new bed is being assigned.
		if ( $newBedId ) {
			$newBed = Bed::find( $newBedId );

			if ( ! $newBed ) {
				throw ValidationException::withMessages( [ 'bed_id' => 'Selected bed does not exist.' ] );
			}
			if ( $newBed->reserved_for_staff ) {
				throw ValidationException::withMessages( [ 'bed_id' => 'This bed is reserved for staff.' ] );
			}
			if ( $newBed->is_occupied && $newBed->user_id !== $student->id && ! $isSudo ) {
				throw ValidationException::withMessages( [ 'bed_id' => 'Selected bed is already occupied.' ] );
			}

			// Assign the new bed.
			$newBed->user_id = $student->id;
			$newBed->is_occupied = true;
			$newBed->save();

			return $newBed->room_id; // Return new room_id
		}

		return null; // Return null as the student is now unassigned from any room.
	}

	/**
	 * Prepares the data array for creating or updating a User.
	 */
	private function prepareUserData( array $data, ?User $user = null ): array {
		$userFillable = array_merge( ( new User() )->getFillable(), [ 'first_name', 'last_name' ] );
		$userData = array_intersect_key( $data, array_flip( $userFillable ) );

		if ( $user ) { // Update
			if ( ! empty( $data['password'] ) ) {
				// log that password is being updated
				$userData['password'] = Hash::make( $data['password'] );
			} else {
				unset( $userData['password'] );
			}
			if ( isset( $userData['first_name'] ) || isset( $userData['last_name'] ) ) {
				$userData['name'] = trim( ( $userData['first_name'] ?? $user->first_name ) . ' ' . ( $userData['last_name'] ?? $user->last_name ) );
			}
		} else { // Create
			$userData['password'] = Hash::make( $data['password'] );
			$userData['status'] = 'pending';
			$userData['role_id'] = Role::where( 'name', 'student' )->first()->id ?? 3;
			if ( isset( $data['first_name'] ) && isset( $data['last_name'] ) ) {
				$userData['name'] = trim( ( $data['first_name'] ?? '' ) . ' ' . ( $data['last_name'] ?? '' ) );
			} elseif ( isset( $data['name'] ) ) {
				// Split the name into first and last names if not provided separately
				$nameParts = explode( ' ', trim( $data['name'] ), 2 );
				$userData['first_name'] = $nameParts[0];
				$userData['last_name'] = $nameParts[1] ?? null;
			}
		}

		unset( $userData['dormitory_id'] ); // Dormitory is derived from the room.
		return $userData;
	}

	/**
	 * Prepares the data array for the StudentProfile.
	 */
	private function prepareProfileData( array $data, bool $isUpdate ): array {
		$profileFillable = ( new StudentProfile() )->getFillable();
		// Prioritize the nested student_profile object if it exists, otherwise use the flat data array.
		$sourceData = $data['student_profile'] ?? $data;
		$profileData = array_intersect_key( $sourceData, array_flip( $profileFillable ) );

		// Ensure student_id is correctly handled for both create and update.
		// The `student_id` might be at the root of `$data` or inside `student_profile`.
		if ( $isUpdate ) {
			// On update, prioritize the `student_id` from the root if it exists.
			$profileData['student_id'] = $data['student_id'] ?? $profileData['student_id'] ?? null;
		} else {
			// On create, it could be in either place. Fallback to IIN if not present.
			$profileData['student_id'] = $data['student_id'] ?? $profileData['student_id'] ?? $profileData['iin'] ?? null;
		}

		// Explicitly cast boolean-like strings to a boolean for database insertion.
		if ( isset( $profileData['agree_to_dormitory_rules'] ) ) {
			$profileData['agree_to_dormitory_rules'] = filter_var( $profileData['agree_to_dormitory_rules'], FILTER_VALIDATE_BOOLEAN );
		}

		return $profileData;
	}

	/**
	 * Handles file uploads and deletion for a StudentProfile.
	 */
	private function processFileUploads( array $data, ?StudentProfile $profile ): array {
		$incomingFiles = $data['files'] ?? null;

		// If no 'files' key is in the request, assume no changes are being made to files.
		if ( ! is_array( $incomingFiles ) ) {
			\Log::info( 'StudentService: No files found in profile data for processing.' );
			return $profile ? ( $profile->files ?? [] ) : [];
		}

		// Start with the existing file paths. This handles "unchanged" files by default.
		$filePaths = ( $profile && is_array( $profile->files ) ) ? $profile->files : array_fill( 0, 4, null );

		foreach ( $incomingFiles as $index => $file ) {
			$oldFile = $filePaths[ $index ] ?? null;

			if ( $file instanceof \Illuminate\Http\UploadedFile ) {
				// New file uploaded: delete the old one and store the new one.
				if ( $oldFile && Storage::disk( 'local' )->exists( $oldFile ) ) {
					Storage::disk( 'local' )->delete( $oldFile );
				}
				$filePaths[ $index ] = $this->storeStudentFile( $file );
			} elseif ( is_string( $file ) && ! empty( $file ) ) {
				// An existing file path was sent back, indicating "no change".
				$filePaths[ $index ] = $file;
			} elseif ( $file === null || $file === '' ) {
				// File marked for removal: delete the old file and set path to null.
				if ( $oldFile && Storage::disk( 'local' )->exists( $oldFile ) ) {
					Storage::disk( 'local' )->delete( $oldFile );
				}
				$filePaths[ $index ] = null;
			}
		}
		// Ensure the array has 4 elements, preserving order.
		return array_replace( array_fill( 0, 3, null ), $filePaths );
	}

	/**
	 * Stores an array of new files and returns their paths.
	 * This is used during student creation.
	 *
	 * @param array $files The array of files from the request.
	 * @return array The array of stored file paths, preserving keys.
	 */
	private function storeNewFiles( array $files ): array {
		$storedPaths = array_fill( 0, 3, null );
		foreach ( $files as $index => $file ) {
			if ( $file instanceof \Illuminate\Http\UploadedFile ) {
				$storedPaths[ $index ] = $this->storeStudentFile( $file );
			}
		}
		return $storedPaths;
	}

	public function listAllStudents(): \Illuminate\Database\Eloquent\Collection {
		$authUser = auth()->user();
		if ( ! $authUser ) {
			return collect();
		}
		$query = User::select( 'id', 'name', 'email' )
			->where( 'role_id', Role::where( 'name', 'student' )->firstOrFail()->id );

		// Sudo can see all students. Admin can only see students from their assigned dormitory.
		if ( $authUser->hasRole( 'admin' ) && ! $authUser->hasRole( 'sudo' ) && $authUser->adminDormitory ) {
			$query->where( 'dormitory_id', $authUser->adminDormitory->id );
		} elseif ( $authUser->hasRole( 'admin' ) && ! $authUser->hasRole( 'sudo' ) ) {
			// Admin with no dormitory assigned sees no students.
			return collect();
		}
		return $query
			->orderBy( 'name', 'asc' )
			->get();
	}

	/**
	 * Stores a student file, preserving the original name if possible.
	 * If a file with the original name exists, it generates a short random name.
	 *
	 * @param \Illuminate\Http\UploadedFile $file The file to store.
	 * @return string The path to the stored file.
	 */
	private function storeStudentFile( \Illuminate\Http\UploadedFile $file ): string {
		$originalFilename = $file->getClientOriginalName();
		$storagePath = 'student_files/' . $originalFilename;

		if ( Storage::disk( 'local' )->exists( $storagePath ) ) {
			// File exists, generate a shorter random name.
			$extension = $file->getClientOriginalExtension();
			$filename = \Illuminate\Support\Str::random( 8 ) . '.' . $extension;
		} else {
			// File does not exist, use the original name.
			$filename = $originalFilename;
		}
		return $file->storeAs( 'student_files', $filename, 'local' );
	}
}
