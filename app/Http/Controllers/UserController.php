<?php
namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use App\Services\UserAuthService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

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

	public function register( Request $request ) {
		$validated = $request->validate( $this->registerRules );

		// Handle file uploads
		$filePaths = [];
		if ( $request->hasFile( 'files' ) ) {
			foreach ( $request->file( 'files' ) as $file ) {
				$filePaths[] = $file->store( 'user_files', 'public' );
			}
			$validated['files'] = $filePaths;
		}

		$validated['password'] = bcrypt( $validated['password'] );
		$validated['status'] = 'pending';
		$validated['role_id'] = Role::where( 'name', 'student' )->first()->id ?? 3;

		$user = User::create( $validated );

		return response()->json( $user, 201 );
	}

	/**
	 * Display a listing of users (admin only)
	 */
	public function index( Request $request ) {
		$query = User::with( [ 'role', 'dormitory' ] )
			->when( $request->search, function ($query, $search) {
				return $query->where( function ($q) use ($search) {
					$q->where( 'first_name', 'like', "%{$search}%" )
						->orWhere( 'last_name', 'like', "%{$search}%" )
						->orWhere( 'email', 'like', "%{$search}%" )
						->orWhere( 'student_id', 'like', "%{$search}%" );
				} );
			} )
			->when( $request->role, function ($query, $role) {
				return $query->whereHas( 'role', function ($q) use ($role) {
					$q->where( 'name', $role );
				} );
			} )
			->when( $request->dormitory_id, function ($query, $dormitoryId) {
				return $query->where( 'dormitory_id', $dormitoryId );
			} );

		$users = $query->paginate( 15 );

		return response()->json( $users );
	}

	/**
	 * Store a newly created user (admin only)
	 */
	public function store( Request $request ) {
		$rules = [ 
			'first_name'        => 'required|string|max:255',
			'last_name'         => 'required|string|max:255',
			'email'             => 'required|email|unique:users,email',
			'password'          => 'required|string|min:6',
			'role_id'           => 'required|exists:roles,id',
			'phone'             => 'nullable|string|max:20',
			'status'            => 'nullable|in:pending,approved,rejected',
			'dormitory_id'      => 'nullable|exists:dormitories,id',

			// Student-specific fields
			'student_id'        => 'nullable|string|max:20|unique:users,student_id',
			'birth_date'        => 'nullable|date',
			'blood_type'        => 'nullable|in:A+,A-,B+,B-,AB+,AB-,O+,O-',
			'course'            => 'nullable|string|max:100',
			'faculty'           => 'nullable|string|max:100',
			'specialty'         => 'nullable|string|max:100',
			'enrollment_year'   => 'nullable|integer|min:1900|max:' . date( 'Y' ),
			'graduation_year'   => 'nullable|integer|min:1900|max:' . ( date( 'Y' ) + 10 ),
			'gender'            => 'nullable|in:male,female',
			'emergency_contact' => 'nullable|string|max:100',
			'emergency_phone'   => 'nullable|string|max:20',
			'violations'        => 'nullable|string',
		];

		$validated = $request->validate( $rules );
		$validated['password'] = Hash::make( $validated['password'] );

		if ( ! isset( $validated['status'] ) ) {
			$validated['status'] = 'approved';
		}

		$user = User::create( $validated );

		return response()->json( $user->load( [ 'role', 'dormitory' ] ), 201 );
	}

	/**
	 * Display the specified user (admin only)
	 */
	public function show( User $user ) {
		return response()->json( $user->load( [ 'role', 'dormitory', 'room' ] ) );
	}

	/**
	 * Update the specified user (admin only)
	 */
	public function update( Request $request, User $user ) {
		$rules = [ 
			'first_name'        => 'sometimes|string|max:255',
			'last_name'         => 'sometimes|string|max:255',
			'email'             => [ 
				'sometimes',
				'email',
				Rule::unique( 'users' )->ignore( $user->id ),
			],
			'password'          => 'sometimes|string|min:6',
			'role_id'           => 'sometimes|exists:roles,id',
			'phone'             => 'nullable|string|max:20',
			'status'            => 'sometimes|in:pending,approved,rejected',
			'dormitory_id'      => 'nullable|exists:dormitories,id',

			// Student-specific fields
			'student_id'        => [ 
					'nullable',
					'string',
					'max:20',
					Rule::unique( 'users' )->ignore( $user->id ),
				],
			'birth_date'        => 'nullable|date',
			'blood_type'        => 'nullable|in:A+,A-,B+,B-,AB+,AB-,O+,O-',
			'course'            => 'nullable|string|max:100',
			'faculty'           => 'nullable|string|max:100',
			'specialty'         => 'nullable|string|max:100',
			'enrollment_year'   => 'nullable|integer|min:1900|max:' . date( 'Y' ),
			'graduation_year'   => 'nullable|integer|min:1900|max:' . ( date( 'Y' ) + 10 ),
			'gender'            => 'nullable|in:male,female',
			'emergency_contact' => 'nullable|string|max:100',
			'emergency_phone'   => 'nullable|string|max:20',
			'violations'        => 'nullable|string',
		];

		$validated = $request->validate( $rules );

		if ( isset( $validated['password'] ) ) {
			$validated['password'] = Hash::make( $validated['password'] );
		}

		$user->update( $validated );

		return response()->json( $user->load( [ 'role', 'dormitory' ] ) );
	}

	/**
	 * Remove the specified user (soft delete)
	 */
	public function destroy( User $user ) {
		$user->delete();

		return response()->json( [ 'message' => 'User deleted successfully' ] );
	}

	/**
	 * Get current user profile
	 */
	public function profile( Request $request ) {
		$user = $request->user()->load( [ 'role', 'dormitory', 'room' ] );
		return response()->json( $user );
	}

	/**
	 * Update current user profile
	 */
	public function updateProfile( Request $request ) {
		$user = $request->user();

		$rules = [ 
			'first_name'        => 'sometimes|string|max:255',
			'last_name'         => 'sometimes|string|max:255',
			'phone'             => 'nullable|string|max:20',
			'emergency_contact' => 'nullable|string|max:100',
			'emergency_phone'   => 'nullable|string|max:20',
		];

		$validated = $request->validate( $rules );

		// Users cannot update their own role or status
		unset( $validated['role_id'], $validated['status'] );

		$user->update( $validated );

		return response()->json( $user->load( [ 'role', 'dormitory' ] ) );
	}

	/**
	 * Change user password
	 */
	public function changePassword( Request $request ) {
		$user = $request->user();

		$validated = $request->validate( [ 
			'current_password' => 'required|string',
			'password'         => 'required|string|min:6|confirmed',
		] );

		if ( ! Hash::check( $validated['current_password'], $user->password ) ) {
			return response()->json( [ 
				'message' => 'The current password is incorrect.',
				'errors'  => [ 'current_password' => [ 'The current password is incorrect.' ] ]
			], 422 );
		}

		$user->update( [ 
			'password' => Hash::make( $validated['password'] )
		] );

		return response()->json( [ 'message' => 'Password updated successfully' ] );
	}
}