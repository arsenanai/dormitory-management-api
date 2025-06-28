<?php

namespace App\Services;

use App\Models\RoomType;

class RoomTypeService {
	public function listRoomTypes() {
		return RoomType::all();
	}

	public function createRoomType( array $data ) {
		return RoomType::create( $data );
	}

	public function findRoomType( $id ) {
		return RoomType::findOrFail( $id );
	}

	public function updateRoomType( RoomType $roomType, array $data ) {
		$roomType->update( $data );
		return $roomType;
	}

	public function deleteRoomType( $id ) {
		$roomType = RoomType::findOrFail( $id );
		$roomType->delete();
	}
}