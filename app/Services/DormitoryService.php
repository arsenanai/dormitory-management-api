<?php

namespace App\Services;

use App\Models\Dormitory;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class DormitoryService {
	public function createDormitory( array $data ) {
		$dorm = Dormitory::create( $data );
		
		// Load admin relationship if admin_id is provided
		if (isset($data['admin_id']) && $data['admin_id']) {
			return $dorm->fresh()->load('admin');
		}
		
		return $dorm;
	}

	public function getById( $id ) {
		return Dormitory::with( [ 'admin', 'rooms.beds' ] )->findOrFail( $id );
	}

	public function updateDormitory( $id, array $data ) {
		\Log::info('DormitoryService updateDormitory called', ['id' => $id, 'data' => $data]);
		
		$dorm = Dormitory::findOrFail( $id );
		\Log::info('Dormitory found', ['dormitory' => $dorm->toArray()]);
		
		$dorm->update( $data );
		\Log::info('Dormitory updated', ['dormitory_after_update' => $dorm->fresh()->toArray()]);
		
		// Load the admin relationship for the response
		return $dorm->fresh()->load('admin');
	}

	public function listDormitories(): JsonResponse {
		$dormitories = Dormitory::with( [ 'admin', 'rooms.beds' ] )->get();

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

		return response()->json( [ 
			'success' => true,
			'data'    => $dormitories
		] );
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
		return $dorm->fresh()->load('admin');
	}

	public function getRoomsForDormitory( $dormitoryId ) {
		$dorm = Dormitory::findOrFail( $dormitoryId );
		return $dorm->rooms()->with( 'roomType' )->get();
	}
}