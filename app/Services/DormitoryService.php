<?php

namespace App\Services;

use App\Models\Dormitory;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class DormitoryService {
	public function createDormitory( array $data ) {
		$dorm = Dormitory::create( $data );

		// Load admin relationship if admin_id is provided
		if ( isset( $data['admin_id'] ) && $data['admin_id'] ) {
			return $dorm->fresh()->load( 'admin' );
		}

		return $dorm;
	}

	public function getById( $id ) {
		return Dormitory::with( [ 'admin', 'rooms.beds' ] )->findOrFail( $id );
	}

	public function updateDormitory( $id, array $data ) {
		\Log::info( 'DormitoryService updateDormitory called', [ 'id' => $id, 'data' => $data ] );

		$dorm = Dormitory::findOrFail( $id );
		\Log::info( 'Dormitory found', [ 'dormitory' => $dorm->toArray() ] );

		$dorm->update( $data );
		\Log::info( 'Dormitory updated', [ 'dormitory_after_update' => $dorm->fresh()->toArray() ] );

		// Load the admin relationship for the response
		return $dorm->fresh()->load( 'admin' );
	}

	public function listDormitories( $user = null ) {
		// Start with base query
		$query = Dormitory::with( [ 'admin', 'rooms.beds' ] );

		// Apply role-based filtering
		if ( $user && $user->role && $user->role->name === 'admin' && $user->dormitory_id ) {
			// Dormitory admin can only see their assigned dormitory
			$query->where( 'id', $user->dormitory_id );
		} elseif ( $user && $user->role && $user->role->name === 'admin' ) {
			// General admin can see all dormitories
			// No additional filtering needed
		} elseif ( $user && $user->role && $user->role->name === 'sudo' ) {
			// Superadmin can see all dormitories
			// No additional filtering needed
		} elseif ( ! $user ) {
			// Unauthenticated users see all dormitories (for public access)
			// No additional filtering needed
		} else {
			// Other roles see no dormitories (or implement as needed)
			$query->where( 'id', 0 ); // This will return empty result
		}

		$dormitories = $query->get();

		// Transform the data to include additional computed fields
		$dormitories = $dormitories->map( function ($dormitory) {
			$dormitory->registered = $dormitory->rooms->sum( function ($room) {
				return $room->beds->where( 'is_occupied', true )->count();
			} );

			$dormitory->freeBeds = $dormitory->rooms->sum( function ($room) {
				return $room->beds->where( 'is_occupied', false )->count();
			} );

			$dormitory->rooms_count = $dormitory->rooms->count();

			return $dormitory;
		} );

		return $dormitories;
	}

	/**
	 * Get all dormitories for public access (student registration, etc.)
	 * This method bypasses role-based filtering
	 */
	public function getAllDormitoriesForPublic() {
		$query = Dormitory::with( [ 'admin', 'rooms.beds' ] );
		$dormitories = $query->get();

		// Transform the data to include additional computed fields
		$dormitories = $dormitories->map( function ($dormitory) {
			$dormitory->registered = $dormitory->rooms->sum( function ($room) {
				return $room->beds->where( 'is_occupied', true )->count();
			} );

			$dormitory->freeBeds = $dormitory->rooms->sum( function ($room) {
				return $room->beds->where( 'is_occupied', false )->count();
			} );

			$dormitory->rooms_count = $dormitory->rooms->count();

			return $dormitory;
		} );

		return $dormitories;
	}

	public function deleteDormitory( $id ) {
		$dorm = Dormitory::findOrFail( $id );
		$dorm->delete();
		return response()->json( [ 'message' => 'Dormitory deleted successfully' ], 200 );
	}

	public function assignAdmin( $dormitoryId, $adminId ) {
		$dorm = Dormitory::findOrFail( $dormitoryId );
		$admin = User::findOrFail( $adminId );
		$dorm->admin()->associate( $admin );
		$dorm->save();
		return $dorm->fresh()->load( 'admin' );
	}

	public function getRoomsForDormitory( $dormitoryId ) {
		$dorm = Dormitory::findOrFail( $dormitoryId );
		return $dorm->rooms()->with( [ 'roomType', 'beds' ] )->get();
	}

	/**
	 * Get dormitory quota information for admin management
	 */
	public function getDormitoryQuotaInfo( $dormitoryId, $user = null ) {
		$dorm = Dormitory::with( [ 'rooms.beds', 'admin' ] )->findOrFail( $dormitoryId );

		// Check if user has access to this dormitory
		if ( $user && $user->role && $user->role->name === 'admin' && $user->dormitory_id !== (int) $dormitoryId ) {
			throw new \Exception( 'Access denied: You can only manage your assigned dormitory' );
		}

		$totalCapacity = $dorm->capacity;
		$totalQuota = $dorm->rooms->sum( 'quota' );
		$occupiedBeds = $dorm->rooms->sum( function ($room) {
			return $room->beds->where( 'is_occupied', true )->count();
		} );
		$availableBeds = $totalQuota - $occupiedBeds;

		return [ 
			'dormitory'  => $dorm,
			'quota_info' => [ 
				'total_capacity'         => $totalCapacity,
				'total_quota'            => $totalQuota,
				'occupied_beds'          => $occupiedBeds,
				'available_beds'         => $availableBeds,
				'utilization_percentage' => $totalQuota > 0 ? round( ( $occupiedBeds / $totalQuota ) * 100, 2 ) : 0
			]
		];
	}

	/**
	 * Update room quota (only for dormitory admin)
	 */
	public function updateRoomQuota( $dormitoryId, $roomId, $quota, $user ) {
		// Check if user has access to this dormitory
		if ( $user && $user->role && $user->role->name === 'admin' && $user->dormitory_id !== (int) $dormitoryId ) {
			throw new \Exception( 'Access denied: You can only manage your assigned dormitory' );
		}

		$room = \App\Models\Room::where( 'id', $roomId )
			->where( 'dormitory_id', $dormitoryId )
			->firstOrFail();

		// Validate quota doesn't exceed room type capacity
		$roomType = \App\Models\RoomType::find( $room->room_type_id );
		if ( $roomType && $quota > $roomType->capacity ) {
			throw new \Exception( 'Room quota cannot exceed room type capacity' );
		}

		$room->update( [ 'quota' => $quota ] );
		return $room->fresh()->load( [ 'dormitory', 'roomType' ] );
	}
}