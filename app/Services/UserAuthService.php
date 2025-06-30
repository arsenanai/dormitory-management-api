<?php
namespace App\Services;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserAuthService {
	public function attemptLogin($email, $password)
	{
		$user = User::where('email', $email)->first();
		if ($user && Hash::check($password, $user->password)) {
			if ($user->role && $user->role->name === 'student' && $user->status !== 'active') {
				return 'not_approved';
			}
			return $user;
		}
		return null;
	}

	public function registerStudent( array $data ) {
		// Handle file uploads
		$filePaths = [];
		if ( isset( $data['files'] ) && is_array( $data['files'] ) ) {
			foreach ( $data['files'] as $file ) {
				$filePaths[] = $file->store( 'user_files', 'public' );
			}
			$data['files'] = $filePaths;
		}

		$data['password'] = bcrypt( $data['password'] );
		$data['status'] = 'pending';
		$data['role_id'] = Role::where( 'name', 'student' )->firstOrFail()->id;

		return User::create( $data );
	}
}