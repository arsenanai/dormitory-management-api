<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;
use App\Models\Bed;
use Illuminate\Validation\ValidationException;

class StudentService {
	/**
	 * Get students with filters and pagination
	 */
	public function getStudentsWithFilters( array $filters = [], User $authUser ) {
		$query = User::whereHas( 'role', fn( $q ) => $q->where( 'name', 'student' ) )->with( [ 'role', 'studentProfile', 'room.beds', 'room.dormitory', 'studentBed' ] );

		// If the user is an admin (but not sudo), restrict to their dormitory. Sudo can see all.
		if ($authUser->hasRole('admin') && !$authUser->hasRole('sudo') && $authUser->adminDormitory) {
			$query->where('dormitory_id', $authUser->adminDormitory->id);
		}

		// Apply filters
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

		$perPage = $filters['per_page'] ?? 20;

		$students = $query->paginate( $perPage );

		return response()->json($students);
	}

	/**
	 * Create a new student
	 */
	public function createStudent( array $data, \App\Models\Dormitory $dormitory ) {
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

		return response()->json( $student->load( [ 'role', 'studentProfile', 'room.dormitory', 'studentBed' ] ), 201 );
	}

	/**
	 * Update student
	 */
	public function updateStudent( $id, array $data, User $authUser ) {
		$student = User::whereHas( 'role', fn( $q ) => $q->where( 'name', 'student' ) )
			->with(['studentBed', 'studentProfile']) // Eager load relationships for efficiency
			->findOrFail( $id );

		// Handle bed assignment and get the new room_id if it changes
		$newRoomId = $this->processBedAssignment($student, $data['bed_id'] ?? null);
		if ($newRoomId !== false) { // `false` indicates no change
			$data['room_id'] = $newRoomId;
		}

		// Prepare data for User and StudentProfile models
		$userData = $this->prepareUserData($data, $student);
		$userData['dormitory_id'] = $authUser->adminDormitory->id;
		$profileData = $this->prepareProfileData($data, true);

		// Handle file uploads, modifying the $profileData array by reference
		$this->processFileUploads($profileData, $student->studentProfile);

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
		return response()->json( $student->fresh()->load( [ 'role', 'studentProfile', 'room.dormitory', 'studentBed' ] ) );
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

		return response()->json( $student->toArray() );
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
		return response()->json( [ 'message' => 'Student deleted successfully' ], 200 );
	}

	/**
	 * Approve student application
	 */
	public function approveStudent( $id ) {
		$student = User::whereHas( 'role', fn( $q ) => $q->where( 'name', 'student' ) )
			->findOrFail( $id );

		$student->status = 'active';
		$student->save();

		return response()->json( [ 
			'message' => 'Student approved successfully',
			'student' => $student->load( [ 'role', 'studentProfile', 'room' ] )
		] );
	}

	/**
	 * Export students to CSV
	 */
	public function exportStudents( array $filters = [], User $authUser ) {
		$query = User::whereHas( 'role', fn( $q ) => $q->where( 'name', 'student' ) )
			->with( [ 'role', 'studentProfile', 'room.dormitory' ] );

		// Filter by the admin's dormitory if the user is an admin
		if ($authUser->hasRole('admin') && $authUser->adminDormitory) {
			$query->where('dormitory_id', $authUser->adminDormitory->id);
		} elseif (isset($filters['dormitory_id'])) {
			$query->where('dormitory_id', $filters['dormitory_id']);
		}

		// Apply same filters as getStudentsWithFilters
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

		$students = $query->get();

		// Create CSV content
		$csvContent = "IIN,Name,Faculty,Specialist,Enrollment Year,Gender,Email,Phone Numbers,Status,Room,Dormitory,City\n";

		foreach ( $students as $student ) {
			$phoneNumbers = is_array( $student->phone_numbers ) ? implode( ';', $student->phone_numbers ) : '';
			$room = $student->room ? $student->room->number : '';
			$dormitory = $student->room && $student->room->dormitory ? $student->room->dormitory->name : '';
			// Use studentProfile for city information
			$city = $student->studentProfile && $student->studentProfile->city ? $student->studentProfile->city : '';
			$iin = $student->studentProfile->iin ?? '';
			$faculty = $student->studentProfile->faculty ?? '';
			$specialist = $student->studentProfile->specialist ?? '';
			$enrollment_year = $student->studentProfile->enrollment_year ?? '';
			$gender = $student->studentProfile->gender ?? '';

			$csvContent .= sprintf(
				"%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n",
				$iin,
				'"' . str_replace( '"', '""', $student->name ) . '"',
				'"' . str_replace( '"', '""', $faculty ) . '"',
				'"' . str_replace( '"', '""', $specialist ) . '"',
				$enrollment_year,
				$gender,
				$student->email,
				'"' . $phoneNumbers . '"',
				$student->status,
				'"' . $room . '"',
				'"' . $dormitory . '"',
				'"' . $city . '"'
			);
		}

		$filename = 'students_export_' . date( 'Y-m-d_H-i-s' ) . '.csv';

		return response( $csvContent )
			->header( 'Content-Type', 'text/csv' )
			->header( 'Content-Disposition', 'attachment; filename="' . $filename . '"' );
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

		return response()->json($students);
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

		return response()->json($students);
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


		return response()->json( [
			'message' => 'Student access updated successfully',
			'student' => $student->load( [ 'role', 'room', 'studentProfile' ] )
		] );
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

		return response()->json( $stats );
	}

	/**
	 * Delete files from storage
	 */
	private function deleteFiles( array $files ) {
		foreach ( $files as $file ) {
			// Ensure the file path is valid and exists before attempting to delete
			if (Storage::disk('public')->exists($file)) {
				Storage::disk( 'public' )->delete( $file );
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
					$filePaths[] = $file->store('student_files', 'public');
				}
			}
		}
		$profileData['files'] = $filePaths;
	}
}
