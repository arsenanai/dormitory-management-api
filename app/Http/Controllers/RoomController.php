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
		'quota'        => 'nullable|integer|min:1',
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
		if ( $user && $user->role && $user->role->name === 'admin' ) {
			$user->load( 'adminProfile' );
		}

		$room = $this->service->findRoom( $id, $user );

		return response()->json( $room, 200 );
	}

	public function store( Request $request ) {
		// Debug logging
		\Log::info( 'RoomController::store called', [ 
			'request_data' => $request->all(),
			'user_id'      => auth()->id(),
			'user_role'    => auth()->user()->role->name ?? 'unknown'
		] );

		// Check if admin user has permission to create room in specified dormitory
		$user = Auth::user();
		if ( $user && $user->role && $user->role->name === 'admin' ) {
			$userDormitoryId = $user->adminProfile?->dormitory_id ?? null;
			$requestDormitoryId = $request->input( 'dormitory_id' );

			if ( $userDormitoryId && $requestDormitoryId && $requestDormitoryId !== $userDormitoryId ) {
				return response()->json( [ 
					'message' => 'Access denied: You can only create rooms in your assigned dormitory',
					'error'   => 'permission_denied'
				], 403 );
			}

			// Force dormitory_id to user's assigned dormitory
			if ( $userDormitoryId ) {
				$request->merge( [ 'dormitory_id' => $userDormitoryId ] );
			}
		}

		\Log::info( 'About to validate request data' );
		try {
			$validated = $request->validate( $this->rules );
			\Log::info( 'Validation passed', [ 'validated_data' => $validated ] );
		} catch (\Illuminate\Validation\ValidationException $e) {
			\Log::error( 'Validation failed', [ 
				'errors'       => $e->errors(),
				'request_data' => $request->all()
			] );
			throw $e;
		}

		// Validate quota doesn't exceed room type capacity
		if ( isset( $validated['quota'] ) ) {
			$roomType = \App\Models\RoomType::find( $validated['room_type_id'] );
			if ( $roomType && $validated['quota'] > $roomType->capacity ) {
				return response()->json( [ 
					'message' => 'Room quota cannot exceed room type capacity',
					'errors'  => [ 'quota' => [ 'Room quota cannot exceed room type capacity' ] ]
				], 422 );
			}
		}

		\Log::info( 'About to call RoomService::createRoom' );
		$room = $this->service->createRoom( $validated, $user );
		\Log::info( 'RoomService::createRoom completed', [ 'room_id' => $room->id ?? 'unknown' ] );

		return response()->json( $room, 201 );
	}

	public function update( Request $request, $id ) {
		$user = Auth::user();
		$room = $this->service->findRoom( $id, $user );

		$updateRules = array_map(
			fn( $rule ) => 'sometimes|' . $rule,
			$this->rules
		);
		$validated = $request->validate( $updateRules );

		// Validate quota doesn't exceed room type capacity
		if ( isset( $validated['quota'] ) ) {
			$roomType = \App\Models\RoomType::find( $validated['room_type_id'] ?? $room->room_type_id );
			if ( $roomType && $validated['quota'] > $roomType->capacity ) {
				return response()->json( [ 
					'message' => 'Room quota cannot exceed room type capacity',
					'errors'  => [ 'quota' => [ 'Room quota cannot exceed room type capacity' ] ]
				], 422 );
			}
		}

		$room = $this->service->updateRoom( $room, $validated, $user );
		return response()->json( $room, 200 );
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
		$isStaff = $user && $user->hasRole( 'admin' ); // Adjust as needed for staff roles

		// Note: Dormitory-based filtering is now handled in RoomService
		// No need to set dormitory_id filter here

		$query = Room::with( [ 'beds' ] );
		if ( $request->has( 'dormitory_id' ) ) {
			$query->where( 'dormitory_id', $request->input( 'dormitory_id' ) );
		}
		$rooms = $query->get();
		$availableRooms = $rooms->map( function ($room) use ($isStaff) {
			$availableBeds = $room->beds->filter( function ($bed) use ($isStaff) {
				if ( $bed->user_id || $bed->is_occupied )
					return false;
				if ( $bed->reserved_for_staff && ! $isStaff )
					return false;
				return true;
			} )->values();
			if ( $availableBeds->isEmpty() )
				return null;
			$room->beds = $availableBeds;
			return $room;
		} )->filter()->values();
		return response()->json( $availableRooms );
	}
}