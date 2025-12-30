<?php

namespace App\Http\Controllers;

use App\Services\DormitoryService;
use App\Services\RoomService;
use Illuminate\Http\Request;

class DormitoryController extends Controller
{
    private array $rules = [
        'name'        => 'required|string|max:255',
        'capacity'    => 'required|integer|min:1',
        'gender'      => 'required|in:male,female,mixed',
        'admin_id'    => 'nullable|integer|exists:users,id',
        'address'     => 'nullable|string|max:500',
        'description' => 'nullable|string|max:1000',
        'phone'       => 'nullable|string|max:20',
    ];

    public function __construct(private DormitoryService $service, private RoomService $roomService)
    {
    }

    public function index(Request $request)
    {
        // Get authenticated user for role-based filtering
        $user = auth()->user();

        // Optionally, you can add filters or pagination here
        $dorms = $this->service->listDormitories($user);
        return response()->json($dorms, 200)
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    /**
     * Get all dormitories for public access (student registration, etc.)
     * This endpoint bypasses role-based filtering
     */
    public function getAllForPublic()
    {
        $dorms = $this->service->getAllDormitoriesForPublic();
        return response()->json($dorms, 200)
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    public function show($id)
    {
        $dorm = $this->service->getById($id);
        return response()->json($dorm, 200)
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->rules);
        $dorm = $this->service->createDormitory($validated);
        return response()->json($dorm, 201);
    }

    public function update(Request $request, $id)
    {
        \Log::info('Dormitory update called', [ 'id' => $id, 'request_data' => $request->all() ]);

        $updateRules = array_map(
            fn ($rule) => 'sometimes|' . $rule,
            $this->rules
        );
        $validated = $request->validate($updateRules);
        \Log::info('Dormitory update validated', [ 'validated_data' => $validated ]);

        $dorm = $this->service->updateDormitory($id, $validated);
        \Log::info('Dormitory update result', [ 'result' => $dorm->toArray() ]);

        return response()->json($dorm, 200)
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    public function destroy($id)
    {
        $this->service->deleteDormitory($id);
        return response()->json([ 'message' => 'Dormitory deleted successfully' ], 200);
    }

    public function assignAdmin(Request $request, $id)
    {
        $dorm = \App\Models\Dormitory::findOrFail($id);
        $admin = \App\Models\User::findOrFail($request->input('admin_id'));
        $this->service->assignAdmin($dorm, $admin);
        return response()->json($dorm, 200);
    }

    public function rooms(Request $request, $id)
    {
        $rooms = $this->service->getRoomsForDormitory($id);
        return response()->json($rooms, 200);
    }

    /**
     * Get dormitory quota information for admin management
     */
    public function getQuotaInfo(Request $request, $dormitory)
    {
        try {
            $user = auth()->user();
            $quotaInfo = $this->service->getDormitoryQuotaInfo($dormitory, $user);
            return response()->json($quotaInfo, 200);
        } catch (\Exception $e) {
            return response()->json([ 'error' => $e->getMessage() ], 403);
        }
    }

    /**
     * Update room quota (admin only)
     */
    public function updateRoomQuota(Request $request, $dormitory, $room)
    {
        $request->validate([
            'quota' => 'required|integer|min:1'
        ]);

        $user = auth()->user();
        $room = $this->service->updateRoomQuota($dormitory, $room, $request->input('quota'), $user);
        return response()->json($room, 200);
    }

    /**
     * Get dormitory details with rooms for registration form (public access)
     */
    public function getForRegistration($id)
    {
        try {
            $dormitory = $this->service->getById($id);
            if (! $dormitory) {
                return response()->json([ 'error' => 'Dormitory not found' ], 404);
            }

            // Load only rooms with available beds for this dormitory
            $rooms = $this->roomService->available($id, 'student');

            $dormitoryData = $dormitory->toArray();
            $dormitoryData['rooms'] = $rooms;

            return response()->json([ 'data' => $dormitoryData ], 200)
                ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
                ->header('Pragma', 'no-cache')
                ->header('Expires', '0');
        } catch (\Exception $e) {
            \Log::error('Failed to get dormitory for registration', [ 'id' => $id, 'error' => $e->getMessage() ]);
            return response()->json([ 'error' => 'Failed to load dormitory data' ], 500);
        }
    }
}
