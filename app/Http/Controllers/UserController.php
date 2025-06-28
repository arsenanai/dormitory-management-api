<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\UserAuthService;

class UserController extends Controller {
	protected $authService;

	public function __construct( UserAuthService $authService ) {
		$this->authService = $authService;
	}

	public function login( Request $request ) {
		$request->validate( [ 
			'email'    => 'required|email',
			'password' => 'required'
		] );

		$user = $this->authService->attemptLogin( $request->email, $request->password );

		if ( ! $user ) {
			return response()->json( [ 'message' => 'Invalid credentials' ], 401 );
		}

		$token = $user->createToken( 'user-token' )->plainTextToken;

		return response()->json( [ 
			'user'  => $user,
			'token' => $token,
		] );
	}
}