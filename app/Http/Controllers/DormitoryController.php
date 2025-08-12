<?php

namespace App\Http\Controllers;

use App\Services\DormitoryService;
use Illuminate\Http\Request;

class DormitoryController extends Controller {
	private array $rules = [ 
		'name'     => 'required|string|max:255',
		'capacity' => 'required|integer|min:1',
		'gender'   => 'required|in:male,female,mixed',
		'admin_id' => 'nullable|integer|exists:users,id',
		'registered' => 'nullable|integer|min:0',
		'freeBeds' => 'nullable|integer|min:0',
		'rooms_count' => 'nullable|integer|min:0',
		'address'  => 'nullable|string|max:500',
		'description' => 'nullable|string|max:1000',
		'quota'    => 'nullable|integer|min:0',
		'phone'    => 'nullable|string|max:20',
	];

	public function __construct( private DormitoryService $service ) {
	}

	public function index( Request $request ) {
		// Optionally, you can add filters or pagination here
		$dorms = $this->service->listDormitories();
		return $dorms->header('Cache-Control', 'no-cache, no-store, must-revalidate')
			->header('Pragma', 'no-cache')
			->header('Expires', '0');
	}

	public function show( $id ) {
		$dorm = $this->service->getById( $id );
		return response()->json( $dorm, 200 )
			->header('Cache-Control', 'no-cache, no-store, must-revalidate')
			->header('Pragma', 'no-cache')
			->header('Expires', '0');
	}

	public function store( Request $request ) {
		$validated = $request->validate( $this->rules );
		$dorm = $this->service->createDormitory( $validated );
		return response()->json( $dorm, 201 );
	}

	public function update( Request $request, $id ) {
		\Log::info('Dormitory update called', ['id' => $id, 'request_data' => $request->all()]);
		
		$updateRules = array_map(
			fn( $rule ) => 'sometimes|' . $rule,
			$this->rules
		);
		$validated = $request->validate( $updateRules );
		\Log::info('Dormitory update validated', ['validated_data' => $validated]);
		
		$dorm = $this->service->updateDormitory( $id, $validated );
		\Log::info('Dormitory update result', ['result' => $dorm->toArray()]);
		
		return response()->json( $dorm, 200 )
			->header('Cache-Control', 'no-cache, no-store, must-revalidate')
			->header('Pragma', 'no-cache')
			->header('Expires', '0');
	}

	public function destroy( $id ) {
		$this->service->deleteDormitory( $id );
		return response()->json( [ 'message' => 'Dormitory deleted successfully' ], 200 );
	}

	public function assignAdmin( Request $request, $id ) {
		$dorm = $this->service->assignAdmin( $id, $request->input( 'admin_id' ) );
		return response()->json( $dorm, 200 );
	}

	public function rooms( Request $request, $id ) {
		$rooms = $this->service->getRoomsForDormitory( $id );
		return response()->json( $rooms, 200 );
	}
}
