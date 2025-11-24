<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Services\AdminService;
use Illuminate\Http\Request;
use function response;

class AdminController extends Controller {
	private int $adminRoleId;
	private array $rules;

	// admin not need student-related fields, but we set defaults for consistency
	private array $defaults = [
		'iin'             => null,
		'faculty'         => 'N/A',
		'specialist'      => 'N/A',
		'enrollment_year' => null,
	];

	public function __construct( private AdminService $service ) {
		$this->adminRoleId = Role::where( 'name', 'admin' )->firstOrFail()->id;
		$this->rules = [
			'first_name'      => 'required|string|max:255',
			'last_name'       => 'required|string|max:255',
			'email'           => 'required|email|max:255|unique:users,email',
			'password'        => 'required|string|min:6|confirmed',
			'phone_numbers'   => 'required|array|min:1',
			'phone_numbers.*' => 'string|max:20',
			'dormitory_id'    => 'required|integer|exists:dormitories,id',
		];
	}

	public function index( Request $request ) {
		$admins = $this->service->listAdmins();
		return response()->json( $admins, 200 );
	}

	public function show( $id ) {
		$admin = $this->service->getAdminById( $id );
		return response()->json( $admin, 200 );
	}

	public function store( Request $request ) {
		// Validate using the rules only (don't merge data defaults into rules)
		$validated = $request->validate( $this->rules );

		// ensure phone_numbers contains at least one non-empty entry
		$phones = array_filter( $validated['phone_numbers'] ?? [], fn( $p ) => is_string( $p ) && trim( $p ) !== '' );
		if ( count( $phones ) === 0 ) {
			return response()->json(
				[
					'message' => 'validation.phone_required',
					'errors'  => [ 'phone_numbers' => [ 'validation.phone_required' ] ],
				],
				422
			);
		}

		// normalize dormitory key: accept dormitory_id or dormitory
		$validated['dormitory_id'] = $validated['dormitory_id'] ?? $validated['dormitory'] ?? null;

		// combine name
		$validated['name'] = trim( $validated['first_name'] . ' ' . $validated['last_name'] );

		$data = array_merge( $this->defaults, $validated );
		$data['role_id'] = $this->adminRoleId;
		$admin = $this->service->createAdmin( $data );
		return response()->json( $admin, 201 );
	}

	public function update( Request $request, $id ) {

		// For updates require essential fields as well
		$updateRules = [
			'first_name'      => 'required|string|max:255',
			'last_name'       => 'required|string|max:255',
			'email'           => 'required|email|max:255|unique:users,email,' . $id,
			'password'        => 'sometimes|nullable|string|min:6|confirmed',
			'phone_numbers'   => 'required|array|min:1',
			'phone_numbers.*' => 'string|max:20',
			'dormitory_id'    => 'required|integer|exists:dormitories,id',
		];

		// $profileRules = [
		// 	'position'        => 'nullable|string|max:255',
		// 	'department'      => 'nullable|string|max:255',
		// 	'office_phone'    => 'nullable|string|max:255',
		// 	'office_location' => 'nullable|string|max:255',
		// ];

		// $updateRules = array_merge( $updateRules, $profileRules );
		$validated = $request->validate( $updateRules );

		// ensure phone numbers non-empty
		$phones = array_filter( $validated['phone_numbers'] ?? [], fn( $p ) => is_string( $p ) && trim( $p ) !== '' );
		if ( count( $phones ) === 0 ) {
			return response()->json(
				[
					'message' => 'validation.phone_required',
					'errors'  => [ 'phone_numbers' => [ 'validation.phone_required' ] ],
				],
				422
			);
		}

		// normalize dormitory key
		$validated['dormitory_id'] = $validated['dormitory_id'] ?? $validated['dormitory'] ?? null;

		// combine name
		$validated['name'] = trim( $validated['first_name'] . ' ' . $validated['last_name'] );

		$data = array_merge( $this->defaults, $validated );
		$admin = $this->service->updateAdmin( $id, $data );
		return response()->json( $admin, 200 );
	}

	public function destroy( $id ) {
		$this->service->deleteAdmin( $id );
		return response()->json( [ 'message' => 'success.admin_deleted' ], 200 );
	}
}
