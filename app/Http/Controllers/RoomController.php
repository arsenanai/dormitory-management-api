<?php

namespace App\Http\Controllers;

use App\Services\RoomService;
use Illuminate\Http\Request;
use App\Models\Room;
use App\Models\Bed;
use Illuminate\Support\Facades\Auth;

class RoomController extends Controller {
	private array $rules = [ 
		'number'       => 'required|string|max:10',
		'floor'        => 'nullable|integer',
		'notes'        => 'nullable|string',
		'dormitory_id' => 'required|exists:dormitories,id',
		'room_type_id' => 'required|exists:room_types,id',
		'beds'		   => 'nullable|array',
	];

	public function __construct( private RoomService $service ) {
	}

	public function index( Request $request ) {
		$filters = $request->only( [ 'dormitory_id', 'room_type_id', 'floor', 'number' ] );
		$perPage = $request->input( 'per_page', 15 );

		// Get authenticated user for role-based filtering
		$user = Auth::user();

		// Load adminProfile relationship for admin users
		if ( $user && $user->role && $user->role->name === 'admin' ) {
			$user->load( 'adminProfile' );
		}

		// Note: Dormitory-based filtering is now handled in RoomService
		// No need to set filters['dormitory_id'] here

		$rooms = $this->service->listRooms( $filters, $perPage, $user );
		return response()->json( $rooms, 200 );
	}

	public function show( $id ) {
		$user = Auth::user();

		// Load adminProfile relationship for admin users

		$room = $this->service->findRoom( $id, $user );

		return response()->json( $room, 200 );
	}

	public function store( Request $request ) {

		// Check if admin user has permission to create room in specified dormitory
		$user = Auth::user();
		if ( $user && $user->role && $user->role->name === 'admin' ) {
			$userDormitoryId = $user->adminDormitory->id ?? null;

			// Force dormitory_id to user's assigned dormitory
			if ( $userDormitoryId ) {
				$request->merge( [ 'dormitory_id' => $userDormitoryId ] );
			}
		}
		try {
			$validated = $request->validate( $this->rules );
		} catch (\Illuminate\Validation\ValidationException $e) {
			throw $e;
		}

		\Log::info( 'About to call RoomService::createRoom' );
		$room = $this->service->createRoom( $validated, $user );
		\Log::info( 'RoomService::createRoom completed', [ 'room_id' => $room->id ?? 'unknown' ] );

		return response()->json( $room, 201 );
	}

	public function update( Request $request, $id ) {
		$validated = $request->validate( $this->rules );
		$room = $this->service->updateRoom( $validated, $id, Auth::user() );
		return response()->json($room, 200);
	}

	public function destroy( $id ) {
		$user = Auth::user();
		$room = $this->service->findRoom( $id, $user );

		$this->service->deleteRoom( $id, $user );
		return response()->json( [ 'message' => 'Room deleted successfully' ], 200 );
	}

	/**
	 * GET /rooms/available
	 * Returns rooms with at least one available bed, and only available beds per room.
	 */
	public function available( Request $request ) {
		$user = Auth::user();
		$isStaff = $user && ($user->hasRole( 'admin' ) || $user->hasRole( 'user' ));
		$dormitoryId = null;

		if ( $request->has( 'dormitory_id' ) ) {
			$dormitoryId = $request->input( 'dormitory_id' );
		} else if ($isStaff) {
			$dormitoryId = $user->adminDormitory->id ?? null;
		}

		$rooms = $this->service->available( $dormitoryId );
		return response()->json( $rooms );
	}
}