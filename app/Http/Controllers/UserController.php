<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use App\Services\UserAuthService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Mail;
use App\Mail\PasswordResetMail;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class UserController extends Controller {
	protected $authService;

	private array $studentRegisterRules = [ 
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

	private array $adminRegisterRules = [ 
		'name'     => 'required|string|max:255',
		'email'    => 'required|email|max:255|unique:users,email',
		'password' => 'required|string|min:6|confirmed',
	];

	private array $guestRegisterRules = [ 
		'name'      => 'required|string|max:255',
		'room_type' => 'required|string',
		'files'     => 'nullable|array|max:4',
		'files.*'   => 'file|mimes:jpg,jpeg,png,pdf|max:2048',
		'email'     => 'nullable|email|max:255|unique:users,email',
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
		$userType = $request->input( 'user_type', 'student' );
		if ( $userType === 'admin' ) {
			$rules = $this->adminRegisterRules;
		} elseif ( $userType === 'guest' ) {
			$rules = $this->guestRegisterRules;
		} else {
			$rules = $this->studentRegisterRules;
		}

		$validated = $request->validate( $rules );

		// Handle file uploads
		$filePaths = [];
		if ( $request->hasFile( 'files' ) ) {
			foreach ( $request->file( 'files' ) as $file ) {
				$filePaths[] = $file->store( 'user_files', 'public' );
			}
			$validated['files'] = $filePaths;
		}

		$validated['password'] = Hash::make( $validated['password'] );
		$validated['status'] = 'pending';

		if ( $userType === 'admin' ) {
			$validated['role_id'] = Role::where( 'name', 'admin' )->first()->id ?? 1;
		} elseif ( $userType === 'guest' ) {
			$validated['role_id'] = Role::where( 'name', 'guest' )->first()->id ?? 4;
		} else {
			$validated['role_id'] = Role::where( 'name', 'student' )->first()->id ?? 3;
		}

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
			'date_of_birth'     => 'nullable|date',
			'blood_type'        => 'nullable|in:A+,A-,B+,B-,AB+,AB-,O+,O-',
			'course'            => 'nullable|string|max:100',
			'faculty'           => 'nullable|string|max:100',
			'specialty'         => 'nullable|string|max:100',
			'enrollment_year'   => 'nullable|integer|min:1900|max:' . date( 'Y' ),
			'graduation_year'   => 'nullable|integer|min:1900|max:' . ( date( 'Y' ) + 10 ),
			'year_of_study'     => 'nullable|integer|min:1|max:6',
			'gender'            => 'nullable|in:male,female',
			'emergency_contact' => 'nullable|string|max:100',
			'emergency_phone'   => 'nullable|string|max:20',
			'violations'        => 'nullable|string',
		];

		$validated = $request->validate( $rules );
		$validated['password'] = Hash::make( $validated['password'] );

		// Generate name from first_name and last_name
		$validated['name'] = $validated['first_name'] . ' ' . $validated['last_name'];

		// Always set admin role_id if user_type is admin
		if ( $request->input( 'user_type' ) === 'admin' ) {
			$validated['role_id'] = Role::where( 'name', 'admin' )->first()->id ?? 1;
		}

		// Reject admin creation attempts here
		if ( $request->input( 'user_type' ) === 'admin' ) {
			return response()->json( [ 'message' => 'Admin creation is not allowed via this endpoint.' ], 403 );
		}

		// Handle phone numbers as array and also store in phone column
		if ( isset( $validated['phone'] ) ) {
			$validated['phone_numbers'] = [ $validated['phone'] ];
			// Keep phone in the phone column as well
		}

		// Handle date_of_birth mapping to birth_date
		if ( isset( $validated['date_of_birth'] ) ) {
			$validated['birth_date'] = $validated['date_of_birth'];
			unset( $validated['date_of_birth'] );
		}

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
			'date_of_birth'     => 'nullable|date',
			'blood_type'        => 'nullable|in:A+,A-,B+,B-,AB+,AB-,O+,O-',
			'course'            => 'nullable|string|max:100',
			'faculty'           => 'nullable|string|max:100',
			'specialty'         => 'nullable|string|max:100',
			'enrollment_year'   => 'nullable|integer|min:1900|max:' . date( 'Y' ),
			'graduation_year'   => 'nullable|integer|min:1900|max:' . ( date( 'Y' ) + 10 ),
			'year_of_study'     => 'nullable|integer|min:1|max:6',
			'gender'            => 'nullable|in:male,female',
			'emergency_contact' => 'nullable|string|max:100',
			'emergency_phone'   => 'nullable|string|max:20',
			'violations'        => 'nullable|string',
		];

		$validated = $request->validate( $rules );

		if ( isset( $validated['password'] ) ) {
			$validated['password'] = Hash::make( $validated['password'] );
		}

		// Update name if first_name or last_name changed
		if ( isset( $validated['first_name'] ) || isset( $validated['last_name'] ) ) {
			$firstName = $validated['first_name'] ?? $user->first_name;
			$lastName = $validated['last_name'] ?? $user->last_name;
			$validated['name'] = $firstName . ' ' . $lastName;
		}

		// Handle phone numbers as array and also store in phone column
		if ( isset( $validated['phone'] ) ) {
			$validated['phone_numbers'] = [ $validated['phone'] ];
			// Keep phone in the phone column as well
		}

		// Handle date_of_birth mapping to birth_date
		if ( isset( $validated['date_of_birth'] ) ) {
			$validated['birth_date'] = $validated['date_of_birth'];
			unset( $validated['date_of_birth'] );
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
		$user = $request->user()->load( [ 'role', 'dormitory', 'room', 'studentProfile', 'guestProfile' ] );

		// Return role-specific profile data
		if ( $user->hasRole( 'student' ) ) {
			// For students, return extended student profile information
			$studentProfile = $user->studentProfile;
			return response()->json( [ 
				'id'                => $user->id,
				'name'              => $user->name,
				'first_name'        => $user->first_name,
				'last_name'         => $user->last_name,
				'email'             => $user->email,
				'phone'             => $user->phone,
				'phone_numbers'     => $user->phone_numbers,
				'role'              => $user->role,
				'room'              => $user->room,
				'dormitory'         => $user->dormitory,
				'dormitory_id'      => $user->dormitory_id,
				// Student-specific fields from StudentProfile
				'student_id'        => $studentProfile?->student_id,
				'faculty'           => $studentProfile?->faculty,
				'specialty'         => $studentProfile?->specialist, // Note: field name difference
				'course'            => $studentProfile?->course,
				'year_of_study'     => $studentProfile?->year_of_study,
				'enrollment_year'   => $studentProfile?->enrollment_year,
				'graduation_year'   => $user->graduation_year, // This might be on User model
				'blood_type'        => $studentProfile?->blood_type,
				'emergency_contact' => $studentProfile?->emergency_contact_name,
				'emergency_phone'   => $studentProfile?->emergency_contact_phone,
				'has_meal_plan'     => $user->has_meal_plan,
				'violations'        => $user->violations,
				'status'            => $user->status,
				'created_at'        => $user->created_at,
				'updated_at'        => $user->updated_at,
			] );
		} elseif ( $user->hasRole( 'guest' ) ) {
			// For guests, return guest-specific profile information
			$guestProfile = $user->guestProfile;
			return response()->json( [ 
				'id'                => $user->id,
				'name'              => $user->name,
				'first_name'        => $user->first_name,
				'last_name'         => $user->last_name,
				'email'             => $user->email,
				'phone'             => $user->phone,
				'phone_numbers'     => $user->phone_numbers,
				'role'              => $user->role,
				'room'              => $user->room,
				// Guest-specific fields from GuestProfile
				'emergency_contact' => $guestProfile?->emergency_contact_name,
				'emergency_phone'   => $guestProfile?->emergency_contact_phone,
				'status'            => $user->status,
				'created_at'        => $user->created_at,
				'updated_at'        => $user->updated_at,
			] );
		} else {
			// For admin and other roles, return basic user information
			return response()->json( [ 
				'id'           => $user->id,
				'name'         => $user->name,
				'first_name'   => $user->first_name,
				'last_name'    => $user->last_name,
				'email'        => $user->email,
				'phone'        => $user->phone,
				'role'         => $user->role,
				'dormitory'    => $user->dormitory,
				'dormitory_id' => $user->dormitory_id,
				'status'       => $user->status,
				'created_at'   => $user->created_at,
				'updated_at'   => $user->updated_at,
			] );
		}
	}

	/**
	 * Update current user profile
	 */
	public function updateProfile( Request $request ) {
		$user = $request->user();

		$rules = [ 
			'first_name'        => 'sometimes|string|max:255',
			'last_name'         => 'sometimes|string|max:255',
			'email'             => 'sometimes|email|max:255|unique:users,email,' . $user->id,
			'phone'             => 'nullable|string|max:20',
			'dormitory_id'      => 'nullable|exists:dormitories,id',
			'emergency_contact' => 'nullable|string|max:100',
			'emergency_phone'   => 'nullable|string|max:20',
		];

		$validated = $request->validate( $rules );

		// Users cannot update their own role or status
		unset( $validated['role_id'], $validated['status'] );

		// Update name if first_name or last_name changed
		if ( isset( $validated['first_name'] ) || isset( $validated['last_name'] ) ) {
			$firstName = $validated['first_name'] ?? $user->first_name;
			$lastName = $validated['last_name'] ?? $user->last_name;
			$validated['name'] = $firstName . ' ' . $lastName;
		}

		// Handle phone numbers as array and also store in phone column
		if ( isset( $validated['phone'] ) ) {
			$validated['phone_numbers'] = [ $validated['phone'] ];
			// Keep phone in the phone column as well
		}

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

	/**
	 * Logout user (revoke current token)
	 */
	public function logout( Request $request ) {
		$request->user()->currentAccessToken()->delete();

		return response()->json( [ 'message' => 'Logged out successfully' ] );
	}

	/**
	 * Send password reset link to user's email
	 */
	public function sendPasswordResetLink( Request $request ) {
		$request->validate( [ 
			'email' => 'required|email'
		] );

		$user = User::where( 'email', $request->email )->first();

		if ( ! $user ) {
			// Don't reveal if email exists or not for security
			return response()->json( [ 
				'message' => 'If this email exists in our system, you will receive a password reset link.'
			] );
		}

		// Generate a password reset token
		$token = \Str::random( 64 );

		// Store in password_resets table (create migration if needed)
		\DB::table( 'password_resets' )->updateOrInsert(
			[ 'email' => $user->email ],
			[ 
				'email'      => $user->email,
				'token'      => Hash::make( $token ),
				'created_at' => now()
			]
		);

		// Send password reset email
		\Mail::to( $user->email )->send( new \App\Mail\PasswordResetMail( $token, $user->email ) );

		return response()->json( [ 
			'message'     => 'If this email exists in our system, you will receive a password reset link.',
			'debug_token' => $token // Remove this in production
		] );
	}

	/**
	 * Reset password using token
	 */
	public function resetPassword( Request $request ) {
		$request->validate( [ 
			'email'    => 'required|email',
			'token'    => 'required|string',
			'password' => 'required|string|min:6|confirmed'
		] );

		$passwordReset = \DB::table( 'password_resets' )
			->where( 'email', $request->email )
			->first();

		if ( ! $passwordReset || ! Hash::check( $request->token, $passwordReset->token ) ) {
			return response()->json( [ 
				'message' => 'Invalid or expired password reset token.'
			], 422 );
		}

		// Check if token is not older than 60 minutes
		if ( now()->diffInMinutes( $passwordReset->created_at ) > 60 ) {
			return response()->json( [ 
				'message' => 'Password reset token has expired.'
			], 422 );
		}

		$user = User::where( 'email', $request->email )->first();

		if ( ! $user ) {
			return response()->json( [ 
				'message' => 'User not found.'
			], 404 );
		}

		// Update password
		$user->update( [ 
			'password' => Hash::make( $request->password )
		] );

		// Delete the used token
		\DB::table( 'password_resets' )->where( 'email', $request->email )->delete();

		return response()->json( [ 
			'message' => 'Password has been reset successfully.'
		] );
	}
}