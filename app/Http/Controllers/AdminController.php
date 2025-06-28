<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Services\AdminService;
use Illuminate\Http\Request;

class AdminController extends Controller
{
	private int $adminRoleId;
	private array $rules;

	// admin not need student-related fields, but we set defaults for consistency
	private array $defaults = [ 
		'iin'             => 'N/A',
		'faculty'         => 'N/A',
		'specialist'      => 'N/A',
		'enrollment_year' => 'N/A',
	];

	public function __construct( private AdminService $service ) {
		$this->adminRoleId = Role::where( 'name', 'admin' )->firstOrFail()->id;
		$this->rules = [ 
			'name'     => 'required|string|max:255',
			'email'    => 'required|email|max:255|unique:users,email',
			'password' => 'required|string|min:6',
			'role_id'  => 'required|integer|in:' . $this->adminRoleId,
			'gender'   => 'required|string|in:male,female',
		];
	}

	public function index( Request $request ) {
		$admins = $this->service->listAdmins();
		return response()->json( $admins, 200 );
	}

	public function store( Request $request ) {
		$validated = $request->validate( $this->rules );

		// Set default values for student-related fields if not provided
		$data = array_merge( $this->defaults, $validated );

		$admin = $this->service->createAdmin( $data );
		return response()->json( $admin, 201 );
	}

	public function update( Request $request, $id ) {
		$updateRules = array_map(
			fn( $rule ) => 'sometimes|' . $rule,
			$this->rules
		);
		$validated = $request->validate( $updateRules );

		// Set default values for student-related fields if not provided
		$data = array_merge( $this->defaults, $validated );

		$admin = $this->service->updateAdmin( $id, $data );
		return response()->json( $admin, 200 );
	}

	public function destroy( $id ) {
		$this->service->deleteAdmin( $id );
		return response()->noContent();
	}
}
