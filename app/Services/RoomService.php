<?php

// filepath: /Users/rsa/lab/dormitory-management-api/app/Services/RoomService.php

namespace App\Services;

use App\Models\Room;

class RoomService {
	public function listRooms( array $filters = [], int $perPage = 15, $user = null ) {
		$query = Room::query();

		// Apply role-based filtering
		if ( $user && $user->role && $user->role->name === 'admin' ) {
			// Get user's assigned dormitory from their profile
			$userDormitoryId = $user->adminProfile?->dormitory_id ?? null;
			if ( $userDormitoryId ) {
				// Dormitory admin can only see rooms in their assigned dormitory
				$query->where( 'dormitory_id', $userDormitoryId );
			} else {
				// Admin without dormitory assignment sees no rooms
				$query->where( 'id', 0 );
			}
		} elseif ( $user && $user->role && $user->role->name === 'sudo' ) {
			// Superadmin can see all rooms
			// No additional filtering needed
		} elseif ( ! $user ) {
			// Unauthenticated users see all rooms (for public access)
			// No additional filtering needed
		} else {
			// Other roles see no rooms (or implement as needed)
			$query->where( 'id', 0 ); // This will return empty result
		}

		// Additional filtering
		if ( isset( $filters['dormitory_id'] ) ) {
			$query->where( 'dormitory_id', $filters['dormitory_id'] );
		}
		if ( isset( $filters['room_type_id'] ) ) {
			$query->where( 'room_type_id', $filters['room_type_id'] );
		}
		if ( isset( $filters['floor'] ) ) {
			$query->where( 'floor', $filters['floor'] );
		}
		if ( isset( $filters['number'] ) ) {
			$query->where( 'number', 'like', '%' . $filters['number'] . '%' );
		}

		// Pagination
		return $query->with( [ 'beds', 'dormitory', 'roomType' ] )->paginate( $perPage );
	}

	public function createRoom( array $data, $user = null ) {
		// Debug logging
		\Log::info( 'RoomService::createRoom called', [ 
			'data'            => $data,
			'fillable_fields' => ( new Room() )->getFillable()
		] );

		try {
			$room = Room::create( $data );

			// Sync beds with room quota after creation
			$this->syncBedsWithQuota( $room );

			\Log::info( 'Room created successfully', [ 'room_id' => $room->id ] );
			return $room;
		} catch (\Exception $e) {
			\Log::error( 'Room creation failed', [ 
				'error' => $e->getMessage(),
				'trace' => $e->getTraceAsString()
			] );
			throw $e;
		}
	}

	public function findRoom( $id, $user = null ) {
		$room = Room::with( [ 'dormitory', 'roomType', 'beds' ] )->findOrFail( $id );

		// Check if admin user has permission to access this room
		if ( $user && $user->role && $user->role->name === 'admin' ) {
			$userDormitoryId = $user->adminProfile?->dormitory_id ?? null;
			if ( $userDormitoryId && $room->dormitory_id !== $userDormitoryId ) {
				abort( 403, 'Access denied: You can only access rooms in your assigned dormitory' );
			}
		}

		return $room;
	}

	public function updateRoom( Room $room, array $data, $user = null ) {
		// Permission check is already done in findRoom before calling this method
		$room->update( $data );

		// Sync beds with room quota after update
		$this->syncBedsWithQuota( $room );

		return $room;
	}

	/**
	 * Ensure the room has the correct number of beds based on its quota
	 */
	private function syncBedsWithQuota( Room $room ) {
		$currentBedCount = $room->beds()->count();
		$requiredBedCount = $room->quota;

		if ( $currentBedCount < $requiredBedCount ) {
			// Create missing beds
			for ( $i = $currentBedCount + 1; $i <= $requiredBedCount; $i++ ) {
				$room->beds()->create( [ 
					'bed_number'         => $i,
					'is_occupied'        => false,
					'reserved_for_staff' => false,
				] );
			}
		} elseif ( $currentBedCount > $requiredBedCount ) {
			// Remove excess beds (only if they're not occupied)
			$excessBeds = $room->beds()
				->where( 'bed_number', '>', $requiredBedCount )
				->where( 'is_occupied', false )
				->whereNull( 'user_id' );

			$excessBeds->delete();
		}
	}

	public function deleteRoom( $id, $user = null ) {
		$room = $this->findRoom( $id, $user );
		$room->delete();
		return response()->json( [ 'message' => 'Room deleted successfully' ], 200 );
	}
}