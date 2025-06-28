<?php
namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserAuthService {
	public function attemptLogin( $email, $password ) {
		$user = User::where( 'email', $email )->first();
		if ( $user && Hash::check( $password, $user->password ) ) {
			return $user;
		}
		return null;
	}
}