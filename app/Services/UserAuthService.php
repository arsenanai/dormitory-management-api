<?php
namespace App\Services;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use App\Models\StudentProfile;
use App\Models\AdminProfile;
use App\Models\GuestProfile;
use Illuminate\Support\Facades\Validator;

class UserAuthService {
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

	public function registerStudent( array $data ) {
		$filePaths = [];
		if ( isset( $data['files'] ) && is_array( $data['files'] ) ) {
			foreach ( $data['files'] as $file ) {
				$filePaths[] = $file->store( 'user_files', 'public' );
			}
			$data['files'] = $filePaths;
		}
		$data['password'] = Hash::make( $data['password'] );
		$data['status'] = 'pending';
		$data['role_id'] = Role::where( 'name', 'student' )->firstOrFail()->id;
		$userFields = [ 'name', 'first_name', 'last_name', 'email', 'phone_numbers', 'room_id', 'password', 'status', 'role_id' ];
		$profileFields = [ 'faculty', 'specialist', 'enrollment_year', 'gender', 'deal_number', 'city_id', 'files', 'agree_to_dormitory_rules' ];
		$userData = array_intersect_key( $data, array_flip( $userFields ) );
		$profileData = array_intersect_key( $data, array_flip( $profileFields ) );
		$user = User::create( $userData );
		$profileData['user_id'] = $user->id;
		StudentProfile::create( $profileData );
		return $user->load( 'studentProfile' );
	}

	public function registerAdmin( array $data ) {
		$data['password'] = Hash::make( $data['password'] );
		$data['status'] = 'active';
		$data['role_id'] = Role::where( 'name', 'admin' )->firstOrFail()->id;
		$userFields = [ 'name', 'first_name', 'last_name', 'email', 'phone_numbers', 'room_id', 'password', 'status', 'role_id' ];
		$profileFields = [ 'position', 'department', 'office_phone', 'office_location' ];
		$userData = array_intersect_key( $data, array_flip( $userFields ) );
		$profileData = array_intersect_key( $data, array_flip( $profileFields ) );
		$user = User::create( $userData );
		$profileData['user_id'] = $user->id;
		AdminProfile::create( $profileData );
		return $user->load( 'adminProfile' );
	}

	public function registerGuest( array $data ) {
		$filePaths = [];
		if ( isset( $data['files'] ) && is_array( $data['files'] ) ) {
			foreach ( $data['files'] as $file ) {
				$filePaths[] = $file->store( 'user_files', 'public' );
			}
			$data['files'] = $filePaths;
		}
		$data['password'] = Hash::make( $data['password'] ?? 'guest_default' );
		$data['status'] = 'pending';
		$data['role_id'] = Role::where( 'name', 'guest' )->firstOrFail()->id;
		$userFields = [ 'name', 'first_name', 'last_name', 'email', 'phone_numbers', 'room_id', 'password', 'status', 'role_id' ];
		$profileFields = [ 'room_type', 'files' ];
		$userData = array_intersect_key( $data, array_flip( $userFields ) );
		$profileData = array_intersect_key( $data, array_flip( $profileFields ) );
		$user = User::create( $userData );
		$profileData['user_id'] = $user->id;
		GuestProfile::create( $profileData );
		return $user->load( 'guestProfile' );
	}

	public function changePassword( User $user, $currentPassword, $newPassword ) {
		if ( ! Hash::check( $currentPassword, $user->password ) ) {
			return false;
		}
		$user->update( [ 'password' => Hash::make( $newPassword ) ] );
		return true;
	}
}