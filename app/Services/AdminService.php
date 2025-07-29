<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use App\Models\AdminProfile;

class AdminService {
	public function listAdmins() {
		// Only list users with the 'admin' role (exclude 'sudo')
		return User::whereHas( 'role', function ($q) {
			$q->where( 'name', 'admin' );
		} )->get( [ 'id', 'name', 'email', 'role_id' ] );
	}

	public function createAdmin( array $data ) {
		$userFields = [ 'name', 'email', 'password', 'role_id' ];
		$profileFields = [ 'position', 'department', 'office_phone', 'office_location' ];
		$userData = array_intersect_key( $data, array_flip( $userFields ) );
		$profileData = array_intersect_key( $data, array_flip( $profileFields ) );
		$userData['password'] = Hash::make( $userData['password'] );
		$user = User::create( $userData );
		$profileData['user_id'] = $user->id;
		AdminProfile::create( $profileData );
		return $user->load( 'adminProfile' );
	}

	public function updateAdmin( $id, array $data ) {
		$admin = User::findOrFail( $id );
		$userFields = [ 'name', 'email', 'password', 'role_id' ];
		$profileFields = [ 'position', 'department', 'office_phone', 'office_location' ];
		$userData = array_intersect_key( $data, array_flip( $userFields ) );
		$profileData = array_intersect_key( $data, array_flip( $profileFields ) );
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