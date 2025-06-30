<?php

namespace App\Http\Controllers;

use App\Services\RoomService;
use Illuminate\Http\Request;

class RoomController extends Controller {
	private array $rules = [ 
		'number'       => 'required|string|max:10',
		'floor'        => 'nullable|integer',
		'notes'        => 'nullable|string',
		'dormitory_id' => 'required|exists:dormitories,id',
		'room_type_id' => 'required|exists:room_types,id',
	];

	public function __construct( private RoomService $service ) {
	}

	public function index( Request $request ) {
		$filters = $request->only( [ 'dormitory_id', 'room_type_id', 'floor', 'number' ] );
		$perPage = $request->input( 'per_page', 15 );
		$rooms = $this->service->listRooms( $filters, $perPage );
		return response()->json( $rooms, 200 );
	}

	public function store( Request $request ) {
		$validated = $request->validate( $this->rules );
		$room = $this->service->createRoom( $validated );
		return response()->json( $room, 201 );
	}

	public function update( Request $request, $id ) {
		$room = $this->service->findRoom( $id );
		$updateRules = array_map(
			fn( $rule ) => 'sometimes|' . $rule,
			$this->rules
		);
		$validated = $request->validate( $updateRules );
		$room = $this->service->updateRoom( $room, $validated );
		return response()->json( $room, 200 );
	}

	public function destroy( $id ) {
		$this->service->deleteRoom( $id );
		return response()->noContent();
	}
}