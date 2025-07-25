<?php

// filepath: /Users/rsa/lab/dormitory-management-api/app/Services/RoomService.php

namespace App\Services;

use App\Models\Room;

class RoomService {
	public function listRooms( array $filters = [], int $perPage = 15 ) {
		$query = Room::query();

		// Filtering
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
		return $query->with( 'beds' )->paginate( $perPage );
	}

	public function createRoom( array $data ) {
		return Room::create( $data );
	}

	public function findRoom( $id ) {
		return Room::findOrFail( $id );
	}

	public function updateRoom( Room $room, array $data ) {
		$room->update( $data );
		return $room;
	}

	public function deleteRoom( $id ) {
		$room = Room::findOrFail( $id );
		$room->delete();
	}
}