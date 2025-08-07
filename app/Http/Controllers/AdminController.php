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
			'name'     => 'required|string|max:255',
			'email'    => 'required|email|max:255|unique:users,email',
			'password' => 'required|string|min:6',
			'gender'   => 'nullable|string|in:male,female',
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
		$rules = array_merge(
			$this->rules,
			[ 
				'position'        => 'nullable|string|max:255',
				'department'      => 'nullable|string|max:255',
				'office_phone'    => 'nullable|string|max:255',
				'office_location' => 'nullable|string|max:255',
			]
		);
		$validated = $request->validate( $rules );
		$data = array_merge( $this->defaults, $validated );
		$data['role_id'] = $this->adminRoleId;
		$admin = $this->service->createAdmin( $data );
		return response()->json( $admin, 201 );
	}

	public function update( Request $request, $id ) {
		// Create update rules with email uniqueness excluding current user
		$updateRules = [ 
			'name'            => 'sometimes|required|string|max:255',
			'surname'         => 'sometimes|nullable|string|max:255',
			'email'           => 'sometimes|required|email|max:255|unique:users,email,' . $id,
			'password'        => 'sometimes|nullable|string|min:6',
			'gender'          => 'sometimes|nullable|string|in:male,female',
			'phone_numbers'   => 'sometimes|nullable|array',
			'phone_numbers.*' => 'sometimes|nullable|string|max:20',
			'dormitory'       => 'sometimes|nullable|integer|exists:dormitories,id',
		];
		$profileRules = [ 
			'position'        => 'nullable|string|max:255',
			'department'      => 'nullable|string|max:255',
			'office_phone'    => 'nullable|string|max:255',
			'office_location' => 'nullable|string|max:255',
		];
		$rules = array_merge( $updateRules, $profileRules );
		$validated = $request->validate( $rules );
		$data = array_merge( $this->defaults, $validated );
		$admin = $this->service->updateAdmin( $id, $data );
		return response()->json( $admin, 200 );
	}

	public function destroy( $id ) {
		$this->service->deleteAdmin( $id );
		return response()->json( [ 'message' => 'Admin deleted successfully' ], 200 );
	}
}
