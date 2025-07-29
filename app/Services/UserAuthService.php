<?php
namespace App\Services;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use App\Models\StudentProfile;
use App\Models\AdminProfile;
use App\Models\GuestProfile;
use Illuminate\Support\Facades\Validator;

/**
 * User Authentication Service
 * 
 * Handles user authentication, registration, and profile management for all user types.
 * Provides centralized authentication logic with role-based access control.
 * 
 * @package App\Services
 * @author SDU Dormitory Management System
 * @version 1.0
 */
class UserAuthService {

	/**
	 * Attempt to authenticate a user with email and password
	 * 
	 * Validates user credentials and checks if the user is approved (for students).
	 * Students must have 'active' status to be able to login.
	 * 
	 * @param string $email User's email address
	 * @param string $password User's password (plain text)
	 * @return User|string|null Returns User object on success, 'not_approved' for pending students, null on failure
	 * 
	 * @example
	 * $user = $authService->attemptLogin('student@example.com', 'password123');
	 * if ($user === 'not_approved') {
	 *     // Handle pending student
	 * } elseif ($user) {
	 *     // Handle successful login
	 * } else {
	 *     // Handle failed login
	 * }
	 */
	public function attemptLogin( $email, $password ) {
		$user = User::where( 'email', $email )->first();
		if ( $user && Hash::check( $password, $user->password ) ) {
			if ( $user->role && $user->role->name === 'student' && $user->status !== 'active' ) {
				return 'not_approved';
			}
			return $user;
		}
		return null;
	}

	/**
	 * Register a new student with comprehensive profile data
	 * 
	 * Creates a new user with 'student' role and associated StudentProfile.
	 * Handles file uploads for required documents and sets default status to 'pending'.
	 * 
	 * @param array $data Student registration data including:
	 *                    - name, email, password, phone_numbers
	 *                    - faculty, specialist, enrollment_year, gender
	 *                    - deal_number, city_id, files (uploaded documents)
	 *                    - agree_to_dormitory_rules
	 * @return User Created user with loaded relationships
	 * @throws \Illuminate\Validation\ValidationException When validation fails
	 * 
	 * @example
	 * $studentData = [
	 *     'name' => 'John Doe',
	 *     'email' => 'john@student.com',
	 *     'password' => 'password123',
	 *     'faculty' => 'Engineering',
	 *     'files' => [$uploadedFile1, $uploadedFile2]
	 * ];
	 * $student = $authService->registerStudent($studentData);
	 */
	public function registerStudent( array $data ) {
		// Handle file uploads for required documents
		$filePaths = [];
		if ( isset( $data['files'] ) && is_array( $data['files'] ) ) {
			foreach ( $data['files'] as $file ) {
				$filePaths[] = $file->store( 'user_files', 'public' );
			}
			$data['files'] = $filePaths;
		}

		// Hash password and set default values
		$data['password'] = Hash::make( $data['password'] );
		$data['status'] = 'pending';
		$data['role_id'] = Role::where( 'name', 'student' )->firstOrFail()->id;

		// Separate user fields from profile fields
		$userFields = [ 'name', 'first_name', 'last_name', 'email', 'phone_numbers', 'room_id', 'password', 'status', 'role_id' ];
		$profileFields = [ 'faculty', 'specialist', 'enrollment_year', 'gender', 'deal_number', 'city_id', 'files', 'agree_to_dormitory_rules' ];

		// Create user record
		$userData = array_intersect_key( $data, array_flip( $userFields ) );
		$user = User::create( $userData );

		// Create student profile
		$profileData = array_intersect_key( $data, array_flip( $profileFields ) );
		$profileData['user_id'] = $user->id;
		StudentProfile::create( $profileData );

		return $user->load( [ 'role', 'studentProfile' ] );
	}

	/**
	 * Register a new guest user
	 * 
	 * Creates a new user with 'guest' role and associated GuestProfile.
	 * Guests have simplified profile requirements compared to students.
	 * 
	 * @param array $data Guest registration data including:
	 *                    - name, email, password, phone_numbers
	 *                    - purpose_of_visit, host_name, host_contact
	 *                    - visit_start_date, visit_end_date, daily_rate
	 * @return User Created user with loaded relationships
	 * @throws \Illuminate\Validation\ValidationException When validation fails
	 */
	public function registerGuest( array $data ) {
		// Hash password and set default values
		$data['password'] = Hash::make( $data['password'] );
		$data['status'] = 'active'; // Guests are typically active immediately
		$data['role_id'] = Role::where( 'name', 'guest' )->firstOrFail()->id;

		// Separate user fields from profile fields
		$userFields = [ 'name', 'first_name', 'last_name', 'email', 'phone_numbers', 'room_id', 'password', 'status', 'role_id' ];
		$profileFields = [ 'purpose_of_visit', 'host_name', 'host_contact', 'visit_start_date', 'visit_end_date', 'daily_rate' ];

		// Create user record
		$userData = array_intersect_key( $data, array_flip( $userFields ) );
		$user = User::create( $userData );

		// Create guest profile
		$profileData = array_intersect_key( $data, array_flip( $profileFields ) );
		$profileData['user_id'] = $user->id;
		GuestProfile::create( $profileData );

		return $user->load( [ 'role', 'guestProfile' ] );
	}

	/**
	 * Register a new admin user
	 * 
	 * Creates a new user with 'admin' role and associated AdminProfile.
	 * Admins have administrative privileges and profile information.
	 * 
	 * @param array $data Admin registration data including:
	 *                    - name, email, password, phone_numbers
	 *                    - position, department, office_phone, office_location
	 * @return User Created user with loaded relationships
	 * @throws \Illuminate\Validation\ValidationException When validation fails
	 */
	public function registerAdmin( array $data ) {
		// Hash password and set default values
		$data['password'] = Hash::make( $data['password'] );
		$data['status'] = 'active'; // Admins are typically active immediately
		$data['role_id'] = Role::where( 'name', 'admin' )->firstOrFail()->id;

		// Separate user fields from profile fields
		$userFields = [ 'name', 'first_name', 'last_name', 'email', 'phone_numbers', 'password', 'status', 'role_id' ];
		$profileFields = [ 'position', 'department', 'office_phone', 'office_location' ];

		// Create user record
		$userData = array_intersect_key( $data, array_flip( $userFields ) );
		$user = User::create( $userData );

		// Create admin profile
		$profileData = array_intersect_key( $data, array_flip( $profileFields ) );
		$profileData['user_id'] = $user->id;
		AdminProfile::create( $profileData );

		return $user->load( [ 'role', 'adminProfile' ] );
	}

	/**
	 * Update user password with validation
	 * 
	 * Validates current password and updates to new password if valid.
	 * 
	 * @param User $user User to update password for
	 * @param string $currentPassword Current password for verification
	 * @param string $newPassword New password to set
	 * @return bool True if password was updated successfully, false otherwise
	 * 
	 * @example
	 * $success = $authService->updatePassword($user, 'oldpass', 'newpass123');
	 * if ($success) {
	 *     // Password updated successfully
	 * } else {
	 *     // Current password was incorrect
	 * }
	 */
	public function updatePassword( User $user, $currentPassword, $newPassword ) {
		// Verify current password
		if ( ! Hash::check( $currentPassword, $user->password ) ) {
			return false;
		}

		// Update to new password
		$user->password = Hash::make( $newPassword );
		$user->save();

		return true;
	}

	/**
	 * Reset user password (admin function)
	 * 
	 * Allows administrators to reset a user's password without requiring the current password.
	 * 
	 * @param User $user User to reset password for
	 * @param string $newPassword New password to set
	 * @return bool True if password was reset successfully
	 */
	public function resetPassword( User $user, $newPassword ) {
		$user->password = Hash::make( $newPassword );
		$user->save();

		return true;
	}

	/**
	 * Check if user has specific role
	 * 
	 * @param User $user User to check
	 * @param string $roleName Role name to check for
	 * @return bool True if user has the specified role
	 */
	public function hasRole( User $user, $roleName ) {
		return $user->role && $user->role->name === $roleName;
	}

	/**
	 * Get user's role name
	 * 
	 * @param User $user User to get role for
	 * @return string|null Role name or null if no role assigned
	 */
	public function getUserRole( User $user ) {
		return $user->role ? $user->role->name : null;
	}

	/**
	 * Validate user data based on role
	 * 
	 * @param array $data User data to validate
	 * @param string $roleName Role name for validation rules
	 * @return \Illuminate\Validation\Validator Validator instance
	 */
	public function validateUserData( array $data, $roleName ) {
		$rules = [ 
			'name'     => 'required|string|max:255',
			'email'    => 'required|email|max:255|unique:users,email',
			'password' => 'required|string|min:6',
		];

		// Add role-specific validation rules
		switch ( $roleName ) {
			case 'student':
				$rules = array_merge( $rules, [ 
					'faculty'                  => 'required|string|max:255',
					'specialist'               => 'required|string|max:255',
					'enrollment_year'          => 'required|integer|min:2000|max:' . ( date( 'Y' ) + 10 ),
					'gender'                   => 'required|in:male,female',
					'deal_number'              => 'required|string|max:255',
					'city_id'                  => 'required|exists:cities,id',
					'files'                    => 'required|array|min:1',
					'files.*'                  => 'file|mimes:pdf,jpg,jpeg,png|max:5120',
					'agree_to_dormitory_rules' => 'required|boolean|accepted',
				] );
				break;

			case 'guest':
				$rules = array_merge( $rules, [ 
					'purpose_of_visit' => 'required|string|max:255',
					'host_name'        => 'required|string|max:255',
					'host_contact'     => 'required|string|max:255',
					'visit_start_date' => 'required|date|after:today',
					'visit_end_date'   => 'required|date|after:visit_start_date',
					'daily_rate'       => 'required|numeric|min:0',
				] );
				break;

			case 'admin':
				$rules = array_merge( $rules, [ 
					'position'        => 'required|string|max:255',
					'department'      => 'required|string|max:255',
					'office_phone'    => 'nullable|string|max:255',
					'office_location' => 'nullable|string|max:255',
				] );
				break;
		}

		return Validator::make( $data, $rules );
	}
}