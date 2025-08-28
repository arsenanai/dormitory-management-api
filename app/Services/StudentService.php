<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use App\Http\Resources\StudentResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;
use App\Models\Bed;
use Illuminate\Validation\ValidationException;

class StudentService {
	/**
	 * Get students with filters and pagination
	 */
	public function getStudentsWithFilters( array $filters = [] ) {
		$query = User::whereHas( 'role', fn( $q ) => $q->where( 'name', 'student' ) )
			->with( [ 'role', 'studentProfile', 'room', 'room.dormitory' ] );

		// Auto restrict by dormitory for admin role if not explicitly filtered
		$authUser = \Illuminate\Support\Facades\Auth::user();
		if ( $authUser && optional( $authUser->role )->name === 'admin' && ! isset( $filters['dormitory_id'] ) ) {
			// Get dormitory_id from AdminProfile relationship
			$adminDormitoryId = $authUser->adminProfile?->dormitory_id;
			if ( $adminDormitoryId ) {
				$filters['dormitory_id'] = (int) $adminDormitoryId;
			}
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

		// Transform the data using StudentResource
		$transformedData = $students->through( function ($student) {
			return new StudentResource( $student );
		} );

		return response()->json( $transformedData );
	}

	/**
	 * Create a new student
	 */
	public function createStudent( array $data ) {
		// Bed assignment validation
		if ( isset( $data['bed_id'] ) ) {
			$bed = Bed::find( $data['bed_id'] );
			if ( ! $bed ) {
				throw ValidationException::withMessages( [ 'bed_id' => 'Selected bed does not exist.' ] );
			}
			if ( $bed->reserved_for_staff ) {
				// Only allow admin users to be assigned to staff-reserved beds
				$roleName = isset( $data['role'] ) ? $data['role'] : ( isset( $data['role_id'] ) ? optional( \App\Models\Role::find( $data['role_id'] ) )->name : null );
				if ( $roleName !== 'admin' ) {
					throw ValidationException::withMessages( [ 'bed_id' => 'Only admin users can be assigned to staff-reserved beds.' ] );
				}
			}
		}

		// Handle file uploads
		$filePaths = [];
		if ( isset( $data['files'] ) ) {
			foreach ( $data['files'] as $file ) {
				$filePaths[] = $file->store( 'student_files', 'public' );
			}
			$data['files'] = $filePaths;
		}

		// Set defaults
		$data['password'] = Hash::make( $data['password'] );
		$data['status'] = 'pending';
		$data['role_id'] = Role::where( 'name', 'student' )->first()->id ?? 3;

		$student = User::create( $data );

		// Create StudentProfile
		$profileData = [ 
			'user_id'                  => $student->id,
			'iin'                      => $data['iin'],
			'student_id'               => $data['student_id'] ?? $data['iin'], // Use IIN as fallback
			'faculty'                  => $data['faculty'],
			'specialist'               => $data['specialist'],
			'enrollment_year'          => $data['enrollment_year'],
			'gender'                   => $data['gender'],
			'blood_type'               => $data['blood_type'] ?? null,
			'parent_name'              => $data['parent_name'] ?? null,
			'parent_phone'             => $data['parent_phone'] ?? null,
			'mentor_name'              => $data['mentor_name'] ?? null,
			'mentor_email'             => $data['mentor_email'] ?? null,
			'violations'               => $data['violations'] ?? null,
			'deal_number'              => $data['deal_number'] ?? null,
			'city_id'                  => $data['city_id'] ?? null,
			'country'                  => $data['country'] ?? null,
			'region'                   => $data['region'] ?? null,
			'city'                     => $data['city'] ?? null,
			'files'                    => ! empty( $filePaths ) ? json_encode( $filePaths ) : null,
			'agree_to_dormitory_rules' => $data['agree_to_dormitory_rules'] ?? false,
		];

		\App\Models\StudentProfile::create( $profileData );

		return response()->json( $student->load( [ 'role', 'city', 'room', 'studentProfile' ] ), 201 );
	}

	/**
	 * Get student details
	 */
	public function getStudentDetails( $id ) {
		$student = User::whereHas( 'role', fn( $q ) => $q->where( 'name', 'student' ) )
			->with( [ 
				'role',
				'studentProfile',
				'city',
				'city.region',
				'city.region.country',
				'room',
				'room.dormitory',
				'room.beds',
				'payments' => function ($q) {
					$q->orderBy( 'created_at', 'desc' );
				}
			] )
			->findOrFail( $id );

		// Add bed information if student has a bed assigned
		if ( $student->room ) {
			$assignedBed = $student->room->beds->where( 'user_id', $student->id )->first();
			if ( $assignedBed ) {
				$student->bed = $assignedBed;
				$student->bed_id = $assignedBed->id;
			}
		}

		return response()->json( $student );
	}

	/**
	 * Update student
	 */
	public function updateStudent( $id, array $data ) {
		// Bed assignment validation
		if ( isset( $data['bed_id'] ) ) {
			$bed = Bed::find( $data['bed_id'] );
			if ( ! $bed ) {
				throw ValidationException::withMessages( [ 'bed_id' => 'Selected bed does not exist.' ] );
			}
			if ( $bed->reserved_for_staff ) {
				$student = User::whereHas( 'role', fn( $q ) => $q->where( 'name', 'student' ) )->find( $id );
				$roleName = $student && $student->role ? $student->role->name : null;
				if ( $roleName !== 'admin' ) {
					throw ValidationException::withMessages( [ 'bed_id' => 'Only admin users can be assigned to staff-reserved beds.' ] );
				}
			}
		}

		$student = User::whereHas( 'role', fn( $q ) => $q->where( 'name', 'student' ) )
			->findOrFail( $id );

		// Handle file uploads
		if ( isset( $data['files'] ) ) {
			// Delete old files if new ones are uploaded
			if ( $student->files ) {
				$this->deleteFiles( $student->files );
			}

			$filePaths = [];
			foreach ( $data['files'] as $file ) {
				$filePaths[] = $file->store( 'student_files', 'public' );
			}
			$data['files'] = $filePaths;
		}

		$student->update( $data );

		// Update StudentProfile if profile-specific fields are provided
		$profileFields = [ 
			'faculty', 'specialist', 'enrollment_year', 'gender', 'blood_type',
			'parent_name', 'parent_phone', 'mentor_name', 'mentor_email',
			'violations', 'deal_number', 'city_id', 'country', 'region', 'city', 'files', 'agree_to_dormitory_rules'
		];

		$profileData = array_intersect_key( $data, array_flip( $profileFields ) );
		if ( ! empty( $profileData ) && $student->studentProfile ) {
			$student->studentProfile->update( $profileData );
		}

		return response()->json( $student->load( [ 'role', 'city', 'room', 'studentProfile' ] ) );
	}

	/**
	 * Delete student
	 */
	public function deleteStudent( $id ) {
		$student = User::whereHas( 'role', fn( $q ) => $q->where( 'name', 'student' ) )
			->findOrFail( $id );

		// Delete associated files
		if ( $student->files ) {
			$this->deleteFiles( $student->files );
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
			'student' => $student->load( [ 'role', 'city', 'room' ] )
		] );
	}

	/**
	 * Export students to CSV
	 */
	public function exportStudents( array $filters = [] ) {
		$query = User::whereHas( 'role', fn( $q ) => $q->where( 'name', 'student' ) )
			->with( [ 'role', 'city', 'room', 'room.dormitory' ] );

		// Auto restrict by dormitory for admin role if not explicitly filtered
		$authUser = \Illuminate\Support\Facades\Auth::user();
		if ( $authUser && optional( $authUser->role )->name === 'admin' && ! isset( $filters['dormitory_id'] ) ) {
			// Get dormitory_id from AdminProfile relationship
			$adminDormitoryId = $authUser->adminProfile?->dormitory_id;
			if ( $adminDormitoryId ) {
				$filters['dormitory_id'] = (int) $adminDormitoryId;
			}
		}

		// Apply same filters as getStudentsWithFilters
		if ( isset( $filters['faculty'] ) ) {
			$query->where( 'faculty', 'like', '%' . $filters['faculty'] . '%' );
		}

		if ( isset( $filters['room_id'] ) ) {
			$query->where( 'room_id', $filters['room_id'] );
		}

		if ( isset( $filters['dormitory_id'] ) ) {
			$query->whereHas( 'room', function ($q) use ($filters) {
				$q->where( 'dormitory_id', $filters['dormitory_id'] );
			} );
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
			$city = $student->city ? $student->city->name : '';

			$csvContent .= sprintf(
				"%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n",
				$student->iin,
				'"' . str_replace( '"', '""', $student->name ) . '"',
				'"' . str_replace( '"', '""', $student->faculty ) . '"',
				'"' . str_replace( '"', '""', $student->specialist ) . '"',
				$student->enrollment_year,
				$student->gender,
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
	public function getStudentsByDormitory( array $filters = [] ) {
		$query = User::whereHas( 'role', fn( $q ) => $q->where( 'name', 'student' ) )
			->with( [ 'role', 'studentProfile', 'room', 'room.dormitory' ] );

		// Auto restrict by dormitory for admin role if not explicitly filtered
		$authUser = \Illuminate\Support\Facades\Auth::user();
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

		// Transform the data using StudentResource
		$transformedData = $students->through( function ($student) {
			return new StudentResource( $student );
		} );

		return response()->json( $transformedData );
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

		// Transform the data using StudentResource
		$transformedData = $students->through( function ($student) {
			return new StudentResource( $student );
		} );

		return response()->json( $transformedData );
	}

	/**
	 * Update student access
	 */
	public function updateStudentAccess( $id, array $data ) {
		$student = User::whereHas( 'role', fn( $q ) => $q->where( 'name', 'student' ) )
			->findOrFail( $id );

		$student->update( $data );

		return response()->json( [ 
			'message' => 'Student access updated successfully',
			'student' => $student->load( [ 'role', 'room', 'studentProfile' ] )
		] );
	}

	/**
	 * Get student statistics
	 */
	public function getStudentStatistics( array $filters = [] ) {
		$query = User::whereHas( 'role', fn( $q ) => $q->where( 'name', 'student' ) );

		// Auto restrict by dormitory for admin role if not explicitly filtered
		$authUser = \Illuminate\Support\Facades\Auth::user();
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
			Storage::disk( 'public' )->delete( $file );
		}
	}
}
