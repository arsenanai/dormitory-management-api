<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminService {
	public function listAdmins() {
		// Only list users with the 'admin' role (exclude 'sudo')
		return User::whereHas( 'role', function ($q) {
			$q->where( 'name', 'admin' );
		} )->get( [ 'id', 'name', 'email', 'role_id' ] );
	}

	public function createAdmin( array $data ) {
		$data['password'] = Hash::make( $data['password'] );
		return User::create( $data );
	}

	public function updateAdmin( $id, array $data ) {
		$admin = User::findOrFail( $id );

		// Only update password if specified
		if ( isset( $data['password'] ) && $data['password'] ) {
			$data['password'] = Hash::make( $data['password'] );
		} else {
			unset( $data['password'] );
		}

		$admin->update( $data );
		return $admin;
	}

	public function deleteAdmin( $id ) {
		$admin = User::findOrFail( $id );
		$admin->delete();
	}
}