<?php
namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use App\Services\UserAuthService;

class UserController extends Controller {
	protected $authService;

	private array $registerRules = [ 
		'iin'                      => 'required|digits:12|unique:users,iin',
		'name'                     => 'required|string|max:255',
		'faculty'                  => 'required|string|max:255',
		'specialist'               => 'required|string|max:255',
		'enrollment_year'          => 'required|integer|digits:4',
		'gender'                   => 'required|in:male,female',
		'email'                    => 'required|email|max:255|unique:users,email',
		'phone_numbers'            => 'nullable|array',
		'phone_numbers.*'          => 'string',
		'room_id'                  => 'nullable|exists:rooms,id',
		'password'                 => 'required|string|min:6|confirmed',
		'deal_number'              => 'nullable|string|max:255',
		'city_id'                  => 'nullable|integer|exists:cities,id',
		'files'                    => 'nullable|array|max:4',
		'files.*'                  => 'file|mimes:jpg,jpeg,png,pdf|max:2048',
		'agree_to_dormitory_rules' => 'required|accepted',
	];

	public function __construct( UserAuthService $authService ) {
		$this->authService = $authService;
	}

	public function login( Request $request ) {
		$request->validate( [ 
			'email'    => 'required|email',
			'password' => 'required'
		] );

		$result = $this->authService->attemptLogin( $request->email, $request->password );

		if ( $result === 'not_approved' ) {
			return response()->json( [ 'message' => 'auth.not_approved' ], 401 );
		}

		if ( ! $result ) {
			return response()->json( [ 'message' => 'auth.invalid_credentials' ], 401 );
		}

		$token = $result->createToken( 'user-token' )->plainTextToken;

		return response()->json( [ 
			'user'  => $result,
			'token' => $token,
		] );
	}

	public function register(Request $request) {
		$validated = $request->validate($this->registerRules);
	
		// Handle file uploads
		$filePaths = [];
		if ($request->hasFile('files')) {
			foreach ($request->file('files') as $file) {
				$filePaths[] = $file->store('user_files', 'public');
			}
			$validated['files'] = $filePaths;
		}
	
		$validated['password'] = bcrypt($validated['password']);
		$validated['status'] = 'pending';
		$validated['role_id'] = Role::where('name', 'student')->first()->id ?? 3;
	
		$user = User::create($validated);
	
		return response()->json($user, 201);
	}
}