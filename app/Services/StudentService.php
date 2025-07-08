<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;

class StudentService {
	/**
	 * Get students with filters and pagination
	 */
	public function getStudentsWithFilters( array $filters = [] ) {
		$query = User::whereHas( 'role', fn( $q ) => $q->where( 'name', 'student' ) )
			->with( [ 'role', 'city', 'city.region', 'city.region.country', 'room', 'room.dormitory' ] );

		// Apply filters
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

		$perPage = $filters['per_page'] ?? 20;

		return response()->json( $query->paginate( $perPage ) );
	}

	/**
	 * Create a new student
	 */
	public function createStudent( array $data ) {
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

		return response()->json( $student->load( [ 'role', 'city', 'room' ] ), 201 );
	}

	/**
	 * Get student details
	 */
	public function getStudentDetails( $id ) {
		$student = User::whereHas( 'role', fn( $q ) => $q->where( 'name', 'student' ) )
			->with( [ 
				'role',
				'city',
				'city.region',
				'city.region.country',
				'room',
				'room.dormitory',
				'payments' => function ($q) {
					$q->orderBy( 'created_at', 'desc' );
				}
			] )
			->findOrFail( $id );

		return response()->json( $student );
	}

	/**
	 * Update student
	 */
	public function updateStudent( $id, array $data ) {
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

		return response()->json( $student->load( [ 'role', 'city', 'room' ] ) );
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
	 * Delete files from storage
	 */
	private function deleteFiles( array $files ) {
		foreach ( $files as $file ) {
			Storage::disk( 'public' )->delete( $file );
		}
	}
}
