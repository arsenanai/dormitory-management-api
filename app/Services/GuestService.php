<?php

namespace App\Services;

use App\Models\User;
use App\Models\Role;
use App\Models\GuestProfile;
use App\Models\Room;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class GuestService {
	/**
	 * Get guests with filters and pagination
	 */
	public function getGuestsWithFilters( array $filters = [] ) {
		$guestRoleId = Role::where( 'name', 'guest' )->first()->id;
		$query = User::where( 'role_id', $guestRoleId )
			->with( [ 'guestProfile', 'room', 'room.dormitory' ] );

		// Apply filters
		if ( isset( $filters['room_id'] ) ) {
			$query->where( 'room_id', $filters['room_id'] );
		}

		if ( isset( $filters['payment_status'] ) ) {
			$query->where( 'payment_status', $filters['payment_status'] );
		}

		if ( isset( $filters['check_in_date'] ) ) {
			$query->whereDate( 'check_in_date', '>=', $filters['check_in_date'] );
		}

		if ( isset( $filters['check_out_date'] ) ) {
			$query->whereDate( 'check_out_date', '<=', $filters['check_out_date'] );
		}

		if ( isset( $filters['search'] ) ) {
			$query->where( function ($q) use ($filters) {
				$q->where( 'name', 'like', '%' . $filters['search'] . '%' )
					->orWhere( 'email', 'like', '%' . $filters['search'] . '%' )
					->orWhere( 'phone', 'like', '%' . $filters['search'] . '%' );
			} );
		}

		$perPage = $filters['per_page'] ?? 15;
		return $query->orderBy( 'check_in_date', 'desc' )->paginate( $perPage );
	}

	/**
	 * Create a new guest
	 */
	public function createGuest( array $data ) {
		DB::beginTransaction();

		try {
			$guestRoleId = Role::where( 'name', 'guest' )->first()->id;
			$data['role_id'] = $guestRoleId;
			$data['status'] = 'active';

			$guest = User::create( $data );

			// Create guest profile
			GuestProfile::create( [ 
				'user_id'          => $guest->id,
				'visit_start_date' => $data['check_in_date'] ?? null,
				'visit_end_date'   => $data['check_out_date'] ?? null,
				'daily_rate'       => $data['total_amount'] ?? 0,
			] );

			// If room is assigned, mark it as occupied
			if ( isset( $data['room_id'] ) ) {
				$room = Room::find( $data['room_id'] );
				if ( $room ) {
					$room->update( [ 'is_occupied' => true ] );
				}
			}

			DB::commit();
			return $guest->load( [ 'guestProfile', 'room', 'room.dormitory' ] );
		} catch (\Exception $e) {
			DB::rollBack();
			throw $e;
		}
	}

	/**
	 * Update guest
	 */
	public function updateGuest( $id, array $data ) {
		DB::beginTransaction();

		try {
			$guest = User::whereHas( 'role', fn( $q ) => $q->where( 'name', 'guest' ) )
				->findOrFail( $id );
			$oldRoomId = $guest->room_id;

			$guest->update( $data );

			// Update guest profile if needed
			if ( isset( $data['check_in_date'] ) || isset( $data['check_out_date'] ) || isset( $data['total_amount'] ) ) {
				$profileData = [];
				if ( isset( $data['check_in_date'] ) )
					$profileData['visit_start_date'] = $data['check_in_date'];
				if ( isset( $data['check_out_date'] ) )
					$profileData['visit_end_date'] = $data['check_out_date'];
				if ( isset( $data['total_amount'] ) )
					$profileData['daily_rate'] = $data['total_amount'];

				if ( $guest->guestProfile ) {
					$guest->guestProfile->update( $profileData );
				}
			}

			// Handle room changes
			if ( isset( $data['room_id'] ) && $data['room_id'] != $oldRoomId ) {
				// Free up old room
				if ( $oldRoomId ) {
					$oldRoom = Room::find( $oldRoomId );
					if ( $oldRoom ) {
						$oldRoom->update( [ 'is_occupied' => false ] );
					}
				}

				// Occupy new room
				if ( $data['room_id'] ) {
					$newRoom = Room::find( $data['room_id'] );
					if ( $newRoom ) {
						$newRoom->update( [ 'is_occupied' => true ] );
					}
				}
			}

			DB::commit();
			return $guest->load( [ 'guestProfile', 'room', 'room.dormitory' ] );
		} catch (\Exception $e) {
			DB::rollBack();
			throw $e;
		}
	}

	/**
	 * Delete guest
	 */
	public function deleteGuest( $id ) {
		DB::beginTransaction();

		try {
			$guest = User::whereHas( 'role', fn( $q ) => $q->where( 'name', 'guest' ) )
				->findOrFail( $id );

			// Free up room if assigned
			if ( $guest->room_id ) {
				$room = Room::find( $guest->room_id );
				if ( $room ) {
					$room->update( [ 'is_occupied' => false ] );
				}
			}

			$guest->delete();

			DB::commit();
			return response()->json( [ 'message' => 'Guest deleted successfully' ] );
		} catch (\Exception $e) {
			DB::rollBack();
			throw $e;
		}
	}

	/**
	 * Get guest by ID
	 */
	public function getGuestById( $id ) {
		return User::whereHas( 'role', fn( $q ) => $q->where( 'name', 'guest' ) )
			->with( [ 'guestProfile', 'room', 'room.dormitory' ] )
			->findOrFail( $id );
	}

	/**
	 * Get available rooms for guests
	 */
	public function getAvailableRooms() {
		return Room::with( [ 'dormitory', 'roomType' ] )
			->where( 'is_occupied', false )
			->get();
	}

	/**
	 * Check guest out
	 */
	public function checkOutGuest( $id ) {
		DB::beginTransaction();

		try {
			$guest = User::whereHas( 'role', fn( $q ) => $q->where( 'name', 'guest' ) )
				->findOrFail( $id );

			$guest->update( [ 
				'check_out_date' => now(),
				'payment_status' => 'paid'
			] );

			// Free up room
			if ( $guest->room_id ) {
				$room = Room::find( $guest->room_id );
				if ( $room ) {
					$room->update( [ 'is_occupied' => false ] );
				}
			}

			DB::commit();
			return $guest->load( [ 'guestProfile', 'room', 'room.dormitory' ] );
		} catch (\Exception $e) {
			DB::rollBack();
			throw $e;
		}
	}

	/**
	 * Export guests to CSV
	 */
	public function exportGuests( array $filters = [] ) {
		$guestRoleId = Role::where( 'name', 'guest' )->first()->id;
		$query = User::where( 'role_id', $guestRoleId )
			->with( [ 'guestProfile', 'room', 'room.dormitory' ] );

		// Apply same filters as getGuestsWithFilters
		if ( isset( $filters['room_id'] ) ) {
			$query->where( 'room_id', $filters['room_id'] );
		}

		if ( isset( $filters['payment_status'] ) ) {
			$query->where( 'payment_status', $filters['payment_status'] );
		}

		if ( isset( $filters['check_in_date'] ) ) {
			$query->whereDate( 'check_in_date', '>=', $filters['check_in_date'] );
		}

		if ( isset( $filters['check_out_date'] ) ) {
			$query->whereDate( 'check_out_date', '<=', $filters['check_out_date'] );
		}

		$guests = $query->get();

		// Create CSV content
		$csvContent = "Name,Email,Phone,Room,Dormitory,Check In,Check Out,Payment Status,Amount,Notes\n";

		foreach ( $guests as $guest ) {
			$csvContent .= sprintf(
				"%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n",
				$guest->name,
				$guest->email,
				$guest->phone,
				$guest->room ? $guest->room->number : '',
				$guest->room && $guest->room->dormitory ? $guest->room->dormitory->name : '',
				$guest->check_in_date,
				$guest->check_out_date,
				$guest->payment_status,
				$guest->total_amount,
				str_replace( [ "\n", "\r" ], ' ', $guest->notes ?? '' )
			);
		}

		$fileName = 'guests_export_' . now()->format( 'Y_m_d_H_i_s' ) . '.csv';
		$filePath = 'exports/' . $fileName;

		Storage::disk( 'public' )->put( $filePath, $csvContent );

		return response()->download( storage_path( 'app/public/' . $filePath ), $fileName, [ 
			'Content-Type' => 'text/csv'
		] );
	}
}
