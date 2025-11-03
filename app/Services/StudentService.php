<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;
use App\Models\Bed;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StudentService {
	/**
	 * Get students with filters and pagination
	 */
	public function getStudentsWithFilters(array $filters = [], User $authUser)
	{
		return $this->buildStudentQuery($filters, $authUser, true);
	}

	/**
	 * Create a new student
	 */
	public function updateStudent( $id, array $data, User $authUser ) {
		return DB::transaction(function () use ($id, $data, $authUser) {
			$student = User::whereHas( 'role', fn( $q ) => $q->where( 'name', 'student' ) )
				->with(['studentBed', 'studentProfile']) // Eager load relationships for efficiency
				->findOrFail( $id );

			// If a new bed/room is being assigned, validate gender compatibility with the new dormitory.
			if (isset($data['bed_id'])) {
				$newBed = Bed::with('room.dormitory')->find($data['bed_id']);
				if ($newBed) {
					$newDormitory = $newBed->room->dormitory;
					$studentGender = $data['gender'] ?? $student->studentProfile->gender;
					if (
						($newDormitory->gender === 'male' && $studentGender === 'female') ||
						($newDormitory->gender === 'female' && $studentGender === 'male')
					) {
						throw ValidationException::withMessages(['bed_id' => 'The selected dormitory does not accept students of this gender.']);
					}
				}
			}

			// Handle bed assignment and get the new room_id if it changes
			$newRoomId = $this->processBedAssignment($student, $data['bed_id'] ?? null);
			if ($newRoomId !== false) { // `false` indicates no change
				$data['room_id'] = $newRoomId;
			}

			// Prepare data for User and StudentProfile models
			$userData = $this->prepareUserData($data, $student);
			$userData['dormitory_id'] = $authUser->adminDormitory->id;
			$profileData = $this->prepareProfileData($data, true);

			// Only process file uploads if new files are actually included in the request.
			if (isset($data['files'])) {
				$this->processFileUploads($profileData, $student->studentProfile);
			}

			// Update the User model with user-specific data.
			if (!empty($userData)) {
				$student->update($userData);
			}

			// Update the StudentProfile model with profile-specific data.
			if (!empty($profileData) && $student->studentProfile) {
				// Ensure student_id is not accidentally nulled out on update
				if (!isset($profileData['student_id'])) {
					$profileData['student_id'] = $student->studentProfile->student_id;
				}
				$student->studentProfile->update( $profileData );
			}

			// Return the fresh, fully-loaded student model.
			return $student->fresh()->load( [ 'role', 'studentProfile', 'room.dormitory', 'studentBed' ] );
		});
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
				'studentBed',
				'payments' => function ($q) {
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
			->with('studentProfile', 'studentBed') // Eager load for cleanup
			->findOrFail( $id );

		// Delete associated files from StudentProfile
		if ($student->studentProfile && !empty($student->studentProfile->files)) {
			$filesToDelete = $student->studentProfile->files;
			// If files are stored as a JSON string, decode it first.
			if (is_string($filesToDelete)) {
				$filesToDelete = json_decode($filesToDelete, true) ?? [];
			}
			$this->deleteFiles(is_array($filesToDelete) ? $filesToDelete : []);
		}

		// Free up the bed if assigned
		if ($student->studentBed) {
			$student->studentBed->user_id = null;
			$student->studentBed->is_occupied = false;
			$student->studentBed->save();
		}

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
		$query = $this->buildStudentQuery($filters, $authUser, false);

		$students = $query->get();

		// Define which columns to export based on the request, defaulting to table columns
		$defaultCols = ['name', 'status', 'enrollment_year', 'faculty', 'dormitory', 'bed', 'phone'];
		$exportCols = isset($filters['columns']) ? explode(',', $filters['columns']) : $defaultCols;

		$headers = array_map('ucfirst', $exportCols);
		$csvContent = implode(',', $headers) . "\n";

		foreach ( $students as $student ) {
			$rowData = [];
			foreach ($exportCols as $col) {
				$value = '';
				switch ($col) {
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
						$value = $student->room ? ($student->room->number . '-' . ($student->studentBed->bed_number ?? '')) : '';
						break;
					case 'phone':
						$value = is_array($student->phone_numbers) ? implode(';', $student->phone_numbers) : ($student->phone ?? '');
						break;
					// Add other cases for any other potential columns
				}
				// Escape quotes for CSV
				$rowData[] = '"' . str_replace('"', '""', $value) . '"';
			}
			$csvContent .= implode(',', $rowData) . "\n";
		}

		$filename = 'students_export_' . date( 'Y-m-d_H-i-s' ) . '.csv';

		return response( $csvContent )
			->header( 'Content-Type', 'text/csv' )
			->header( 'Content-Disposition', 'attachment; filename="' . $filename . '"' );
	}

	private function buildStudentQuery(array $filters = [], User $authUser, bool $paginate = true) {
		$query = User::whereHas( 'role', fn( $q ) => $q->where( 'name', 'student' ) )->with( [ 'role', 'studentProfile', 'room.dormitory', 'studentBed' ] );

		// Filter by the admin's dormitory if the user is an admin or if the flag is set
		if (
			($filters['my_dormitory_only'] ?? false) ||
			($authUser->hasRole('admin') && !$authUser->hasRole('sudo') && $authUser->adminDormitory)
		) {
			$query->where('dormitory_id', $authUser->adminDormitory->id);
		}

		// Apply other filters
		if ( isset( $filters['faculty'] ) ) {
			$query->whereHas( 'studentProfile', function ($q) use ($filters) {
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
		if (isset($filters['search'])) {
			$search = $filters['search'];
			$query->where(function ($q) use ($search) {
				$q->where('name', 'like', "%{$search}%")
				  ->orWhere('email', 'like', "%{$search}%")
				  ->orWhereHas('studentProfile', function ($sq) use ($search) {
					  $sq->where('faculty', 'like', "%{$search}%")
						 ->orWhere('iin', 'like', "%{$search}%");
				  })
				  ->orWhereHas('room', function ($rq) use ($search) {
					  $rq->where('number', 'like', "%{$search}%");
				  });
			});
		}

		if ($paginate) {
			$perPage = $filters['per_page'] ?? 20;
			return $query->paginate($perPage);
		}

		return $query;
	}

	/**
	 * Get students with filters and pagination
	 */
	public function createStudent( array $data, \App\Models\Dormitory $dormitory ) {
		return DB::transaction(function () use ($data, $dormitory) {
			// Validate that the student's gender is compatible with the dormitory's gender policy.
			if (
				($dormitory->gender === 'male' && $data['gender'] === 'female') ||
				($dormitory->gender === 'female' && $data['gender'] === 'male')
			) {
				throw ValidationException::withMessages(['room_id' => 'The selected dormitory does not accept students of this gender.']);
			}

			// Prepare data for User and StudentProfile models
			$userData = $this->prepareUserData($data);
			$profileData = $this->prepareProfileData($data, false);

			// Handle file uploads, modifying the $profileData array by reference
			$this->processFileUploads($profileData, null);

			// If a bed is assigned, update the user's room_id
			if (isset($data['bed_id'])) {
				$bed = Bed::find($data['bed_id']);
				if ($bed) {
					$userData['room_id'] = $bed->room_id;
				}
			}
			$userData['dormitory_id'] = $dormitory->id;
			// Create the User
			$student = User::create( $userData );

			// Create the StudentProfile
			$profileData['user_id'] = $student->id;
			\App\Models\StudentProfile::create( $profileData );

			// Assign bed if provided
			$this->processBedAssignment($student, $data['bed_id'] ?? null);

			return $student->load( [ 'role', 'studentProfile', 'room.dormitory', 'studentBed' ] );
		});
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
			$query->whereHas( 'room', function ($q) use ($filters) {
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
			$query->whereHas( 'studentProfile', function ($q) use ($filters) {
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

		if (isset($data['has_access'])) {
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
		if (isset($filters['dormitory_id'])) {
			$query->where('dormitory_id', $filters['dormitory_id']);
		} elseif ($authUser->hasRole('admin') && $authUser->adminDormitory) {
			$query->where('dormitory_id', $authUser->adminDormitory->id);
		}

		if ( isset( $filters['faculty'] ) ) {
			$query->whereHas( 'studentProfile', function ($q) use ($filters) {
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
		foreach ( $files as $file ) {
			// Ensure the file path is valid and exists before attempting to delete from 'local' disk
			if (Storage::disk('local')->exists($file)) {
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
	 * @throws ValidationException
	 */
	private function processBedAssignment(User $student, ?int $newBedId) {
		$oldBed = $student->studentBed;
		$oldBedId = $oldBed->id ?? null;

		if ($newBedId === $oldBedId) {
			return false; // No change in bed assignment.
		}

		// Free up the old bed if it exists.
		if ($oldBed) {
			$oldBed->user_id = null;
			$oldBed->is_occupied = false;
			$oldBed->save();
		}

		// If a new bed is being assigned.
		if ($newBedId) {
			$newBed = Bed::find($newBedId);

			if (!$newBed) {
				throw ValidationException::withMessages(['bed_id' => 'Selected bed does not exist.']);
			}
			if ($newBed->reserved_for_staff) {
				throw ValidationException::withMessages(['bed_id' => 'This bed is reserved for staff.']);
			}
			if ($newBed->is_occupied && $newBed->user_id !== $student->id) {
				throw ValidationException::withMessages(['bed_id' => 'Selected bed is already occupied.']);
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
	private function prepareUserData(array $data, ?User $user = null): array {
		$userFillable = array_merge((new User())->getFillable(), ['first_name', 'last_name']);
		$userData = array_intersect_key($data, array_flip($userFillable));

		if ($user) { // Update
			if (!empty($data['password'])) {
				// log that password is being updated
				$userData['password'] = Hash::make($data['password']);
			} else {
				unset($userData['password']);
			}
			if (isset($userData['first_name']) || isset($userData['last_name'])) {
				$userData['name'] = trim(($userData['first_name'] ?? $user->first_name) . ' ' . ($userData['last_name'] ?? $user->last_name));
			}
		} else { // Create
			$userData['password'] = Hash::make($data['password']);
			$userData['status'] = 'pending';
			$userData['role_id'] = Role::where('name', 'student')->first()->id ?? 3;
			if (isset($data['first_name']) && isset($data['last_name'])) {
				$userData['name'] = trim(($userData['first_name'] ?? '') . ' ' . ($userData['last_name'] ?? ''));
			} elseif (isset($data['name'])) {
				// Split the name into first and last names if not provided separately
				$nameParts = explode(' ', trim($data['name']), 2);
				$userData['first_name'] = $nameParts[0];
				$userData['last_name'] = $nameParts[1] ?? null;
			}
		}

		unset($userData['dormitory_id']); // Dormitory is derived from the room.
		return $userData;
	}

	/**
	 * Prepares the data array for the StudentProfile.
	 */
	private function prepareProfileData(array $data, bool $isUpdate): array {
		$profileFillable = (new \App\Models\StudentProfile())->getFillable();
		// Prioritize the nested student_profile object if it exists, otherwise use the flat data array.
		$sourceData = $data['student_profile'] ?? $data;
		$profileData = array_intersect_key($sourceData, array_flip($profileFillable));

		// Ensure student_id is correctly handled for both create and update.
		// The `student_id` might be at the root of `$data` or inside `student_profile`.
		if ($isUpdate) {
			// On update, prioritize the `student_id` from the root if it exists.
			$profileData['student_id'] = $data['student_id'] ?? $profileData['student_id'] ?? null;
		} else {
			// On create, it could be in either place. Fallback to IIN if not present.
			$profileData['student_id'] = $data['student_id'] ?? $profileData['student_id'] ?? $profileData['iin'] ?? null;
		}

		// Explicitly cast boolean-like strings to a boolean for database insertion.
		if (isset($profileData['agree_to_dormitory_rules'])) {
			$profileData['agree_to_dormitory_rules'] = filter_var($profileData['agree_to_dormitory_rules'], FILTER_VALIDATE_BOOLEAN);
		}

		return $profileData;
	}

	/**
	 * Handles file uploads and deletion for a StudentProfile.
	 */
	private function processFileUploads(array &$profileData, ?\App\Models\StudentProfile $profile): void {
		if (!isset($profileData['files'])) return;

		if ($profile && !empty($profile->files)) {
			$this->deleteFiles(is_array($profile->files) ? $profile->files : []);
		}

		$filePaths = [];
		if (is_array($profileData['files'])) {
			foreach ($profileData['files'] as $file) {
				if ($file instanceof \Illuminate\Http\UploadedFile) {
					$filePaths[] = $file->store('student_files', 'local');
				}
			}
		}
		$profileData['files'] = $filePaths;
	}
}
