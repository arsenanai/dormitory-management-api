<?php

namespace App\Services;

use App\Models\Dormitory;
use App\Models\User;

class DormitoryService
{
    public function createDormitory(array $data)
    {
        $dorm = Dormitory::create($data);
        $admin = User::findOrFail($data['admin_id']);
        $this->assignAdmin($dorm, $admin);
        return $dorm;
    }

    public function getById($id)
    {
        return Dormitory::with([ 'admin', 'rooms.beds' ])->findOrFail($id);
    }

    public function updateDormitory($id, array $data)
    {
        $dorm = Dormitory::findOrFail($id);
        $dorm->update($data);
        if (array_key_exists('admin_id', $data)) {
            $admin = User::findOrFail($data['admin_id']);
            $this->assignAdmin($dorm, $admin);
        }
        return $dorm->fresh()->load('admin');
    }

    public function listDormitories($user = null, ?string $occupantType = null)
    {
        // Start with base query
        $query = Dormitory::with([ 'admin' ]);

        if ($occupantType) {
            $query->with([ 'rooms' => function ($q) use ($occupantType) {
                $q->where('occupant_type', $occupantType)->with('beds');
            } ]);
        } else {
            $query->with([ 'rooms.beds' ]);
        }

        // Apply role-based filtering
        if ($user && $user->role && $user->role->name === 'admin' && $user->adminDormitory->id) {
            // Dormitory admin can only see their assigned dormitory
            $query->where('id', $user->adminDormitory->id);
        } elseif ($user && $user->role && $user->role->name === 'admin') {
            // General admin can see all dormitories
            // No additional filtering needed
        } elseif ($user && $user->role && $user->role->name === 'sudo') {
            // Superadmin can see all dormitories
            // No additional filtering needed
        } elseif (! $user) {
            // Unauthenticated users see all dormitories (for public access)
            // No additional filtering needed
        } else {
            // Other roles see no dormitories (or implement as needed)
            $query->where('id', 0); // This will return empty result
        }

        $dormitories = $query->get();

        // Transform the data to include additional computed fields
        $dormitories = $dormitories->map(function ($dormitory) use ($occupantType) {
            // If occupantType is specified, we already filtered rooms in the query.
            // If not, we use all rooms.
            $relevantRooms = $dormitory->rooms;

            $dormitory->setAttribute('registered', $relevantRooms->reduce(function ($carry, $room) {
                return $carry + $room->beds->where('is_occupied', true)->count();
            }, 0));

            $dormitory->setAttribute('freeBeds', $relevantRooms->reduce(function ($carry, $room) {
                return $carry + $room->beds->where('is_occupied', false)
                    ->whereNull('user_id')
                    ->where('reserved_for_staff', false)
                    ->count();
            }, 0));

            $dormitory->setAttribute('rooms_count', $relevantRooms->count());

            // Hide rooms to optimize response size for listings
            return $dormitory->makeHidden([ 'rooms' ]);
        });

        return $dormitories;
    }

    /**
     * Get all dormitories for public access (student registration, etc.)
     * This method bypasses role-based filtering
     */
    public function getAllDormitoriesForPublic(?string $occupantType = null)
    {
        $query = Dormitory::with([ 'admin' ]);

        if ($occupantType) {
            $query->with([ 'rooms' => function ($q) use ($occupantType) {
                $q->where('occupant_type', $occupantType)->with('beds');
            } ]);
        } else {
            $query->with([ 'rooms.beds' ]);
        }

        $dormitories = $query->get();

        // Transform the data to include additional computed fields
        $dormitories = $dormitories->map(function ($dormitory) use ($occupantType) {
            $relevantRooms = $dormitory->rooms;

            $dormitory->setAttribute('registered', $relevantRooms->reduce(function ($carry, $room) {
                return $carry + $room->beds->where('is_occupied', true)->count();
            }, 0));

            $dormitory->setAttribute('freeBeds', $relevantRooms->reduce(function ($carry, $room) {
                return $carry + $room->beds->where('is_occupied', false)
                    ->whereNull('user_id')
                    ->where('reserved_for_staff', false)
                    ->count();
            }, 0));

            $dormitory->setAttribute('rooms_count', $relevantRooms->count());

            // Hide rooms to optimize response size for listings
            return $dormitory->makeHidden([ 'rooms' ]);
        });

        return $dormitories;
    }

    public function deleteDormitory($id)
    {
        $dorm = Dormitory::findOrFail($id);
        $dorm->delete();
        return response()->json([ 'message' => 'Dormitory deleted successfully' ], 200);
    }

    public function assignAdmin($dormitory, $admin)
    {
        // The 'dormitories' table has the 'admin_id'.
        // So, we associate the admin with the dormitory model and save it.
        $dormitory->admin()->associate($admin)->save();
    }

    public function getRoomsForDormitory($dormitoryId)
    {
        $dorm = Dormitory::findOrFail($dormitoryId);
        return $dorm->rooms()->with([ 'roomType', 'beds' ])->get();
    }

    /**
     * Get dormitory quota information for admin management
     */
    public function getDormitoryQuotaInfo($dormitoryId, $user = null)
    {
        $dorm = Dormitory::with([ 'rooms.beds', 'admin' ])->findOrFail($dormitoryId);

        // Check if user has access to this dormitory
        if ($user && $user->role && $user->role->name === 'admin' && $user->adminDormitory->id !== (int) $dormitoryId) {
            throw new \Exception('Access denied: You can only manage your assigned dormitory');
        }

        $totalCapacity = $dorm->capacity;
        $totalQuota = $dorm->rooms->sum('quota');
        $occupiedBeds = $dorm->rooms->reduce(function ($carry, $room) {
            return $carry + $room->beds->where('is_occupied', true)->count();
        }, 0);
        $availableBeds = $totalQuota - $occupiedBeds;

        return [
            'dormitory'  => $dorm,
            'quota_info' => [
                'total_capacity'         => $totalCapacity,
                'total_quota'            => $totalQuota,
                'occupied_beds'          => $occupiedBeds,
                'available_beds'         => $availableBeds,
                'utilization_percentage' => $totalQuota > 0 ? round(($occupiedBeds / $totalQuota) * 100, 2) : 0
            ]
        ];
    }

    /**
     * Update room quota (only for dormitory admin)
     */
    public function updateRoomQuota($dormitoryId, $roomId, $quota, $user)
    {
        // Check if user has access to this dormitory
        if ($user && $user->role && $user->role->name === 'admin' && $user->adminDormitory->id !== (int) $dormitoryId) {
            throw new \Exception('Access denied: You can only manage your assigned dormitory');
        }

        $room = \App\Models\Room::where('id', $roomId)
            ->where('dormitory_id', $dormitoryId)
            ->firstOrFail();

        // Validate quota doesn't exceed room type capacity
        $roomType = \App\Models\RoomType::find($room->room_type_id);
        if ($roomType && $quota > $roomType->capacity) {
            throw new \Exception('Room quota cannot exceed room type capacity');
        }

        $room->update([ 'quota' => $quota ]);
        return $room->fresh()->load([ 'dormitory', 'roomType' ]);
    }
}
