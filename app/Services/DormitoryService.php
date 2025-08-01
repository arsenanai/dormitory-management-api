<?php

namespace App\Services;

use App\Models\Dormitory;
use App\Models\User;

class DormitoryService {
	public function createDormitory( array $data ) {
		return Dormitory::create( $data );
	}

	public function updateDormitory( $id, array $data ) {
		$dorm = Dormitory::findOrFail( $id );
		$dorm->update( $data );
		return $dorm;
	}

	public function listDormitories() {
		return Dormitory::with( 'admin' )->get();
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
		return $dorm;
	}

	public function getRoomsForDormitory( $dormitoryId ) {
		$dorm = Dormitory::findOrFail( $dormitoryId );
		return $dorm->rooms()->with( 'roomType' )->get();
	}
}