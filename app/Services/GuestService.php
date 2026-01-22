<?php

namespace App\Services;

use App\Models\Bed;
use App\Models\GuestProfile;
use App\Models\Role;
use App\Models\Room;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class GuestService {
	public function __construct() {
		
	}
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

		// Payment filtering disabled temporarily
		// if ( isset( $filters['payment_status'] ) ) {
		//     $query->where( 'payment_status', $filters['payment_status'] );
		// }

		if ( isset( $filters['check_in_date'] ) ) {
			$query->whereHas( 'guestProfile', function ( $q ) use ( $filters ) {
				$q->whereDate( 'visit_start_date', '>=', $filters['check_in_date'] );
			} );
		}

		if ( isset( $filters['check_out_date'] ) ) {
			$query->whereHas( 'guestProfile', function ( $q ) use ( $filters ) {
				$q->whereDate( 'visit_end_date', '<=', $filters['check_out_date'] );
			} );
		}

		if ( isset( $filters['search'] ) ) {
			$query->where( function ( $q ) use ( $filters ) {
				$q->where( 'name', 'like', '%' . $filters['search'] . '%' )
					->orWhere( 'email', 'like', '%' . $filters['search'] . '%' )
					->orWhereRaw( "phone_numbers::text ILIKE ?", [ '%' . $filters['search'] . '%' ] );
			} );
		}

		$perPage = $filters['per_page'] ?? 15;
		return $query->orderBy( 'created_at', 'desc' )->paginate( $perPage );
	}

	/**
	 * Create a new guest
	 */
	public function createGuest( array $data ) {
		DB::beginTransaction();
		try {
			$guestRole = Role::where( 'name', 'guest' )->first();
			$guestRoleId = $guestRole->id;
			$auth = auth()->user();
			if ( ! isset( $data['dormitory_id'] ) && $auth->hasRole( 'admin' ) ) {
				$data['dormitory_id'] = $auth->adminDormitory->id;
			}
			if ( ! isset( $data['password'] ) ) {
				$data['password'] = Hash::make( config( 'constants.GUEST_DEFAULT_PASSWORD' ) );
			}
			// Only pass valid User fields
			$userData = [
				'first_name'    => $data['first_name'],
				'last_name'     => $data['last_name'],
				'name'          => $data['first_name'] . ' ' . $data['last_name'],
				'email'         => $data['email'] ?? null,
				'phone_numbers' => isset( $data['phone'] ) ? [ $data['phone'] ] : [],
				'room_id'       => $data['room_id'] ?? null,
				'role_id'       => $guestRoleId,
				'status'        => 'active',
				'password'      => Hash::make( $data['password'] ),
				'dormitory_id'  => $data['dormitory_id'],
			];

			// Debug logging
			Log::info( 'Creating guest with userData:', $data );

			$guest = User::create( $userData );

			$dailyRate = 0;
			if ( isset( $data['total_amount'] ) && isset( $data['check_in_date'] ) && isset( $data['check_out_date'] ) ) {
				$startDate = Carbon::parse( $data['check_in_date'] );
				$endDate = Carbon::parse( $data['check_out_date'] );
				$days = $endDate->diffInDays( $startDate );
				if ( $days > 0 && $data['total_amount'] > 0 ) {
					$dailyRate = $data['total_amount'] / $days;
				} else {
					// Fallback to room type's daily rate if calculation is not possible
					$room = Room::with( 'roomType' )->find( $data['room_id'] );
					$dailyRate = $room?->roomType?->daily_rate ?? 0;
				}
			}

			// Create guest profile with profile-specific fields
			GuestProfile::create( [
				'user_id'                 => $guest->id,
				'visit_start_date'        => $data['check_in_date'] ?? null,
				'visit_end_date'          => $data['check_out_date'] ?? null,
				'daily_rate'              => $dailyRate,
				'purpose_of_visit'        => $data['notes'] ?? null,
				'host_name'               => $data['host_name'] ?? null,
				'host_contact'            => $data['host_contact'] ?? null,
				'identification_type'     => $data['identification_type'] ?? null,
				'identification_number'   => $data['identification_number'] ?? null,
				'emergency_contact_name'  => $data['emergency_contact_name'] ?? null,
				'emergency_contact_phone' => $data['emergency_contact_phone'] ?? null,
				'bed_id'                  => $data['bed_id'] ?? null,
				'reminder'                => $data['reminder'] ?? null,
			] );

			// If room is assigned, mark it as occupied
			if ( isset( $data['room_id'] ) ) {
				$room = Room::find( $data['room_id'] );
				if ( $room ) {
					$room->update( [ 'is_occupied' => true ] );
				}
			}

			// If bed is assigned, mark it as occupied
			if ( isset( $data['bed_id'] ) ) {
				$bed = Bed::find( $data['bed_id'] );
				if ( $bed ) {
					$bed->update( [ 'is_occupied' => true, 'user_id' => $guest->id ] );
				}
			}


			DB::commit();
			return $guest->load( [ 'guestProfile', 'room', 'room.dormitory' ] );
		} catch (\Exception $e) {
			Log::error( 'Error creating guest:', [ 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString() ] );
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
			$auth = auth()->user();
			$guest = User::whereHas( 'role', fn( $q ) => $q->where( 'name', 'guest' ) )
				->findOrFail( $id );
			$oldRoomId = $guest->room_id;
			$oldBedId = $guest->guestProfile?->bed_id;

			// If first_name or last_name are provided, construct the full 'name'
			if ( isset( $data['first_name'] ) || isset( $data['last_name'] ) ) {
				$firstName = $data['first_name'] ?? $guest->first_name;
				$lastName = $data['last_name'] ?? $guest->last_name;
				$data['name'] = trim( $firstName . ' ' . $lastName );
			}
			if ( ! isset( $data['dormitory_id'] ) && $auth->hasRole( 'admin' ) ) {
				$data['dormitory_id'] = $auth->adminDormitory->id;
			}
			$guest->update( $data );

			// Update guest profile if needed
			if ( isset( $data['check_in_date'] ) || isset( $data['check_out_date'] ) || isset( $data['total_amount'] ) || isset( $data['notes'] ) || isset( $data['bed_id'] ) ) {
				$profileData = [];
				if ( isset( $data['check_in_date'] ) ) {
					$profileData['visit_start_date'] = $data['check_in_date'];
				}
				if ( isset( $data['check_out_date'] ) ) {
					$profileData['visit_end_date'] = $data['check_out_date'];
				}
				// if ( isset( $data['total_amount'] ) )
				//     $profileData['daily_rate'] = $data['total_amount'];
				if ( isset( $data['notes'] ) ) {
					$profileData['purpose_of_visit'] = $data['notes'];
				}
				if ( isset( $data['bed_id'] ) ) {
					$profileData['bed_id'] = $data['bed_id'];
				}

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

			// Handle bed changes
			if ( isset( $data['bed_id'] ) && $data['bed_id'] != $oldBedId ) {
				// Free up old bed
				if ( $oldBedId ) {
					$oldBed = Bed::find( $oldBedId );
					if ( $oldBed ) {
						$oldBed->update( [ 'is_occupied' => false, 'user_id' => null ] );
					}
				}
				// Occupy new bed
				$newBed = Bed::find( $data['bed_id'] );
				if ( $newBed ) {
					$newBed->update( [ 'is_occupied' => true, 'user_id' => $guest->id ] );
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

			// Free up bed if assigned
			if ( $guest->guestProfile && $guest->guestProfile->bed_id ) {
				$bed = Bed::find( $guest->guestProfile->bed_id );
				if ( $bed ) {
					$bed->update( [ 'is_occupied' => false, 'user_id' => null ] );
				}
			}

			// Free up room if assigned
			if ( $guest->room_id ) {
				$room = Room::find( $guest->room_id );
				if ( $room ) {
					$room->update( [ 'is_occupied' => false ] );
				} // This might be redundant if bed management implies room occupancy
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
			->with( [
				'guestProfile',
				'room',
				'room.roomType',
				'room.dormitory' ] )
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
				// 'payment_status' => 'paid' // marking payment status disabled
			] );

			// Free up room
			if ( $guest->room_id ) {
				$room = Room::find( $guest->room_id );
				if ( $room ) {
					$room->update( [ 'is_occupied' => false ] );
				}
			}

			// Free up bed
			if ( $guest->guestProfile && $guest->guestProfile->bed_id ) {
				$bed = Bed::find( $guest->guestProfile->bed_id );
				if ( $bed ) {
					$bed->update( [ 'is_occupied' => false, 'user_id' => null ] );
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

		// Payment filtering disabled temporarily
		// if ( isset( $filters['payment_status'] ) ) {
		//     $query->where( 'payment_status', $filters['payment_status'] );
		// }

		if ( isset( $filters['check_in_date'] ) ) {
			$query->whereDate( 'check_in_date', '>=', $filters['check_in_date'] );
		}

		if ( isset( $filters['check_out_date'] ) ) {
			$query->whereDate( 'check_out_date', '<=', $filters['check_out_date'] );
		}

		$guests = $query->get();

		// Create CSV content
		$csvContent = "Name,Email,Phone,Room,Dormitory,Check In,Check Out,Notes\n";

		foreach ( $guests as $guest ) {
			$csvContent .= sprintf(
				"%s,%s,%s,%s,%s,%s,%s,%s\n",
				$guest->first_name . " " . $guest->last_name,
				$guest->email,
				$guest->phone_numbers ? implode( '; ', $guest->phone_numbers ) : '',
				$guest->room ? $guest->room->number : '',
				$guest->room && $guest->room->dormitory ? $guest->room->dormitory->name : '',
				$guest->guestProfile->visit_start_date,
				$guest->guestProfile->visit_end_date,
				// $guest->payment_status,
				// $guest->total_amount,
				str_replace( [ "\n", "\r" ], ' ', $guest->notes ?? '' )
			);
		}

		$fileName = 'guests_export_' . now()->format( 'Y_m_d_H_i_s' ) . '.csv';

		return response( $csvContent )
			->header( 'Content-Type', 'text/csv' )
			->header( 'Content-Disposition', 'attachment; filename="' . $fileName . '"' );
	}

	public function listAll(): \Illuminate\Database\Eloquent\Collection {
		$authUser = auth()->user();
		if ( ! $authUser ) {
			return collect();
		}
		$query = User::select( 'id', 'name', 'email' )
			->where( 'role_id', Role::where( 'name', 'guest' )->firstOrFail()->id );

		// Sudo can see all students. Admin can only see students from their assigned dormitory.
		if ( $authUser->hasRole( 'admin' ) && ! $authUser->hasRole( 'sudo' ) && $authUser->adminDormitory ) {
			$query->where( 'dormitory_id', $authUser->adminDormitory->id );
		} elseif ( $authUser->hasRole( 'admin' ) && ! $authUser->hasRole( 'sudo' ) ) {
			// Admin with no dormitory assigned sees no students.
			return collect();
		}
		return $query
			->orderBy( 'name', 'asc' )
			->get();
	}
}
