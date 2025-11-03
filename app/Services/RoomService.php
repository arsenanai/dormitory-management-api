<?php

// filepath: /Users/rsa/lab/dormitory-management-api/app/Services/RoomService.php

namespace App\Services;

use App\Models\Room;
use App\Models\Bed;
use Illuminate\Support\Facades\DB;

class RoomService {
	public function listRooms( array $filters = [], int $perPage = 15, $user = null ) {
		$query = Room::query();

		// Apply role-based filtering
		if ( $user && $user->role && $user->role->name === 'admin' ) {
			// Get user's assigned dormitory from their profile
			$userDormitoryId = $user->adminDormitory->id ?? null;
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
		return DB::transaction(function () use ($data) {
			$room = Room::create( $data );
			if (isset($data['beds'])) {
				$this->syncBeds( $room, $data );
			}
			return $room;
		});
	}

	public function findRoom( $id, $admin = null ) {
		try {
			$userDormitoryId = $admin->adminDormitory->id;
			$room = Room::where( 'dormitory_id', $userDormitoryId )
				->with( [ 'dormitory', 'roomType', 'beds.user' ] )->findOrFail( $id );
			return $room;
		} catch (\Throwable $e) { return null; }
	}

	public function updateRoom( array $data, $roomId, $user = null ) {
		return DB::transaction(function () use ($data, $roomId, $user) {
			$room = Room::with( [ 'beds', 'dormitory', 'roomType' ] )->findOrFail( $roomId );
			if ( $user->hasRole('admin') && $room->dormitory->id !== $user->adminDormitory->id )
				throw new \Exception('unauthorized ' . $room->dormitory->id . ' vs ' . $user->adminDormitory->id);
			$room->update( $data );
			if (isset($data['beds'])) {
				$this->syncBeds( $room, $data );
			}
			return $room->refresh()->load( [ 'beds', 'dormitory', 'roomType' ] );
		});
	}

	/**
	 * Ensure the room has the correct number of beds based on its quota
	 */
	private function syncBeds( Room $room, array $data ) {
		foreach( $room->beds as $index => $bed ) {
			if ( isset( $data['beds'][ $index ] ) ) {
				$bedData = $data['beds'][ $index ];
				$bed->reserved_for_staff = $bed->is_occupied ? false : $bedData['reserved_for_staff'] ?? $bed->reserved_for_staff;
				$bed->save();
			}
		}

		for( $i = count($room->beds); $i < count($data['beds']); $i++ ) {
			$bedData = $data['beds'][ $i ];
			Bed::create( [
				'room_id'            => $room->id,
				'bed_number'         => $i + 1,
				'reserved_for_staff' => $bedData['reserved_for_staff'] ?? false,
				'is_occupied'        => false,
				'user_id'			 => null,
			] );
		}
	}

	public function deleteRoom( $id, $user = null ) {
		$room = $this->findRoom( $id, $user );
		$room->delete();
		return response()->json( [ 'message' => 'Room deleted successfully' ], 200 );
	}

	public function available( $dormitoryId ) {
		$query = Room::whereHas('beds', function ($bedQuery) {
			$bedQuery->where('reserved_for_staff', false);
		})->with(['beds' => function ($bedQuery) {
			$bedQuery->where('reserved_for_staff', false);
		}, 'dormitory', 'roomType']);

		if ( $dormitoryId ) {
			$query->where( 'dormitory_id', $dormitoryId );
		}
		$rooms = $query->get();
		return $rooms;
	}
}