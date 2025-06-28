<?php

namespace App\Http\Controllers;

use App\Services\DormitoryService;
use Illuminate\Http\Request;

class DormitoryController extends Controller
{
	private array $rules = [ 
		'name'     => 'required|string|max:255',
		'capacity' => 'required|integer|min:1',
	];

	public function __construct( private DormitoryService $service ) {
	}

	public function index( Request $request ) {
		// Optionally, you can add filters or pagination here
		$dorms = $this->service->listDormitories();
		return response()->json( $dorms, 200 );
	}

	public function store( Request $request ) {
		$validated = $request->validate( $this->rules );
		$dorm = $this->service->createDormitory( $validated );
		return response()->json( $dorm, 201 );
	}

	public function update( Request $request, $id ) {
		$updateRules = array_map(
			fn( $rule ) => 'sometimes|' . $rule,
			$this->rules
		);
		$validated = $request->validate( $updateRules );
		$dorm = $this->service->updateDormitory( $id, $validated );
		return response()->json( $dorm, 200 );
	}

	public function destroy( $id ) {
		$this->service->deleteDormitory( $id );
		return response()->noContent();
	}

	public function assignAdmin( Request $request, $id ) {
		$dorm = $this->service->assignAdmin( $id, $request->input( 'admin_id' ) );
		return response()->json( $dorm, 200 );
	}
}
