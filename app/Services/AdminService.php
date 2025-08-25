<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use App\Models\AdminProfile;

class AdminService {
	public function listAdmins() {
		// Only list users with the 'admin' role (exclude 'sudo')
		return User::whereHas( 'role', function ($q) {
			$q->where( 'name', 'admin' );
		} )->with( 'adminProfile' )->get( [ 'id', 'name', 'first_name', 'last_name', 'email', 'role_id', 'dormitory_id' ] );
	}

	public function getAdminById( $id ) {
		// Get a specific admin by ID with their profile
		return User::whereHas( 'role', function ($q) {
			$q->where( 'name', 'admin' );
		} )->where( 'id', $id )->with( 'adminProfile' )->firstOrFail();
	}

	public function createAdmin( array $data ) {
		$userFields = [ 'name', 'last_name', 'email', 'password', 'role_id', 'phone_numbers' ];
		$profileFields = [ 'position', 'department', 'office_phone', 'office_location' ];
		$userData = array_intersect_key( $data, array_flip( $userFields ) );
		$profileData = array_intersect_key( $data, array_flip( $profileFields ) );

		// Debug logging
		Log::info( 'AdminService::createAdmin - Input data:', $data );
		Log::info( 'AdminService::createAdmin - User data:', $userData );

		$userData['password'] = Hash::make( $userData['password'] );
		$user = User::create( $userData );

		// Debug logging
		Log::info( 'AdminService::createAdmin - Created user:', $user->toArray() );

		$profileData['user_id'] = $user->id;
		AdminProfile::create( $profileData );
		return $user->load( 'adminProfile' );
	}

	public function updateAdmin( $id, array $data ) {
		$admin = User::findOrFail( $id );
		$userFields = [ 'name', 'surname', 'email', 'password', 'role_id', 'phone_numbers', 'dormitory_id', 'dormitory' ];
		$profileFields = [ 'position', 'department', 'office_phone', 'office_location' ];
		$userData = array_intersect_key( $data, array_flip( $userFields ) );
		$profileData = array_intersect_key( $data, array_flip( $profileFields ) );

		// Handle name mapping (surname -> last_name)
		if ( isset( $userData['surname'] ) ) {
			$userData['last_name'] = $userData['surname'];
			unset( $userData['surname'] );
		}

		// Handle dormitory mapping (dormitory -> dormitory_id)
		if ( isset( $userData['dormitory'] ) ) {
			$userData['dormitory_id'] = $userData['dormitory'];
			unset( $userData['dormitory'] );
		}
		if ( isset( $userData['password'] ) && $userData['password'] ) {
			$userData['password'] = Hash::make( $userData['password'] );
		} else {
			unset( $userData['password'] );
		}
		$admin->update( $userData );
		if ( $admin->adminProfile ) {
			$admin->adminProfile->update( $profileData );
		} else if ( ! empty( $profileData ) ) {
			$profileData['user_id'] = $admin->id;
			AdminProfile::create( $profileData );
		}
		return $admin->load( 'adminProfile' );
	}

	public function deleteAdmin( $id ) {
		$admin = User::findOrFail( $id );
		$admin->forceDelete(); // Hard delete instead of soft delete
		return response()->json( [ 'message' => 'Admin deleted successfully' ], 200 );
	}
}