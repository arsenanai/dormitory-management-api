<?php

namespace App\Http\Controllers;

use App\Models\Bed;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BedController extends Controller
{
    /**
     * Update the specified bed
     */
    public function update(Request $request, Bed $bed): JsonResponse
    {
        // Check if user has permission to access this bed's room
        /** @var User $user */
        $user = Auth::user();
        /** @var \App\Models\Role|null $role */
        $role = $user->role;
        if ($role && $role->name === 'admin') {
            $userDormitoryId = $user->adminProfile?->dormitory_id; // @phpstan-ignore-line
            /** @var \App\Models\Room|null $room */
            $room = $bed->room;
            if ($userDormitoryId && $room && $room->dormitory_id !== $userDormitoryId) {
                return response()->json(['error' => 'Access denied: You can only modify beds in your assigned dormitory'], 403);
            }
        }

        // Validate request
        $validated = $request->validate([
            'reserved_for_staff' => 'boolean',
            'is_occupied' => 'boolean',
        ]);

        // Update bed
        $bed->update($validated);

        return response()->json($bed, 200);
    }
}
