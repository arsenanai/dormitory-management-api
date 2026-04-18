<?php

namespace App\Http\Controllers;

use App\Models\Dormitory;
use App\Models\Role;
use App\Models\User;
use App\Services\RoomService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class RoomController extends Controller
{
    public function __construct(private RoomService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only([ 'dormitory_id', 'room_type_id', 'floor', 'number', 'occupant_type', 'status' ]);
        $perPageValue = $request->input('per_page');
        $perPage = $perPageValue !== null && is_numeric($perPageValue) ? (int) $perPageValue : 15;

        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        if ($this->isAdminUser($user)) {
            $user?->load('adminProfile');
        }

        $rooms = $this->service->listRooms($filters, $perPage, $user);
        return response()->json($rooms, 200);
    }

    public function show(int $id): JsonResponse
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        $room = $this->service->findRoom($id, $user);

        return response()->json($room, 200);
    }

    public function store(Request $request): JsonResponse
    {

        $user = Auth::user();
        if ($this->isAdminUser($user)) {
            $adminDormitory = $this->getAdminDormitory($user);
            $userDormitoryId = $adminDormitory?->id;

            if ($userDormitoryId !== null) {
                $request->merge([ 'dormitory_id' => $userDormitoryId ]);
            }
        }
        try {
            $dormitoryIdValue = $request->input('dormitory_id');
            $dormitoryId = $dormitoryIdValue !== null ? (int) $dormitoryIdValue : 0;
            $rules = [
                'number'         => [
                    'required', 'string', 'max:10',
                    Rule::unique('rooms')->where('dormitory_id', $dormitoryId)
                ],
                'floor'          => 'nullable|integer',
                'notes'          => 'nullable|string',
                'dormitory_id'   => 'required|exists:dormitories,id',
                'room_type_id'   => 'required|exists:room_types,id',
                'occupant_type'  => [ 'required', Rule::in([ 'student', 'guest' ]) ],
                'is_maintenance' => 'boolean',
            ];
            $validated = $request->validate($rules);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        }

        if ($request->has('beds')) {
            $validated['beds'] = $request->input('beds');
        }
        \Log::info('About to call RoomService::createRoom');
        $room = $this->service->createRoom($validated, $user);
        \Log::info('RoomService::createRoom completed', [ 'room_id' => $room->id ?? 'unknown' ]);

        return response()->json($room, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $dormitoryIdValue = $request->input('dormitory_id');
        $dormitoryId = $dormitoryIdValue !== null ? (int) $dormitoryIdValue : 0;
        $rules = [
            'number'         => [
                'required', 'string', 'max:10',
                Rule::unique('rooms')->where('dormitory_id', $dormitoryId)->ignore($id)
            ],
            'floor'          => 'nullable|integer',
            'notes'          => 'nullable|string',
            'dormitory_id'   => 'required|exists:dormitories,id',
            'room_type_id'   => 'required|exists:room_types,id',
            'occupant_type'  => [ 'required', Rule::in([ 'student', 'guest' ]) ],
            'is_maintenance' => 'boolean',
        ];
        $validated = $request->validate($rules);

        if ($request->has('beds')) {
            $validated['beds'] = $request->input('beds');
        }

        $room = $this->service->updateRoom($validated, $id, Auth::user());
        return response()->json($room, 200);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $room = $this->service->findRoom($id, $user);

        $this->service->deleteRoom($id, $user);
        return response()->json([ 'message' => 'Room deleted successfully' ], 200);
    }

    public function available(Request $request): JsonResponse
    {
        $user = Auth::user();
        $isStaff = $user !== null && ($user->hasRole('admin') || $user->hasRole('user'));
        $dormitoryId = null;

        if ($request->has('dormitory_id')) {
            $dormitoryIdValue = $request->input('dormitory_id');
            $dormitoryId = $dormitoryIdValue !== null ? (int) $dormitoryIdValue : null;
        } elseif ($isStaff) {
            $adminDormitory = $this->getAdminDormitory($user);
            $dormitoryId = $adminDormitory?->id;
        }

        $params = $request->only([ 'start_date', 'end_date', 'guest_id' ]);

        $rooms = $this->service->available(
            $dormitoryId,
            $request->input('occupant_type', 'student'),
            $params
        );
        return response()->json($rooms);
    }

    public function listAll(): JsonResponse
    {
        $user = Auth::user();
        $adminDormitory = $this->getAdminDormitory($user);
        if ($adminDormitory === null) {
            return response()->json([ 'message' => 'no_dormitory_assigned'
            ], 403);
        }
        $rooms = $this->service->listAllRoomsInDormitory($adminDormitory->id);
        return response()->json($rooms, 200);
    }

    private function isAdminUser(?User $user): bool
    {
        if ($user === null) {
            return false;
        }
        $role = $user->role;
        return $role instanceof Role && $role->name === 'admin';
    }

    private function getAdminDormitory(?User $user): ?Dormitory
    {
        if ($user === null) {
            return null;
        }
        return $user->adminDormitory;
    }
}
