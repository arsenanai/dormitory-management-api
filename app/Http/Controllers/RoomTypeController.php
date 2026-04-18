<?php

namespace App\Http\Controllers;

use App\Services\RoomTypeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoomTypeController extends Controller
{
    /** @var array<string, string> */
    private array $rules = [
        'name'            => 'sometimes|string|max:255',
        'capacity'        => 'sometimes|integer|min:1',
        'daily_rate'      => 'sometimes|numeric|min:0',
        'semester_rate'   => 'sometimes|numeric|min:0',
        'photos'          => 'sometimes|array|max:10',
        'photos.*'        => 'sometimes|image|mimes:jpg,jpeg,png,webp|max:5120',
        'existing_photos' => 'sometimes|json',
    ];

    public function __construct(private RoomTypeService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $roomTypes = $this->service->listRoomTypes();
        return response()->json($roomTypes, 200);
    }

    public function show(int $id): JsonResponse
    {
        $roomType = $this->service->findRoomType($id);

        if (!$roomType) {
            return response()->json(['message' => 'Room type not found'], 404);
        }

        return response()->json($roomType, 200);
    }

    public function store(Request $request): JsonResponse
    {
        $rules = $this->rules;
        $rules['name'] = 'required|string|max:255';
        $rules['capacity'] = 'required|integer|min:1';
        $rules['daily_rate'] = 'required|numeric|min:0';
        $rules['semester_rate'] = 'required|numeric|min:0';
        $rules['photos'] = 'required|array|min:1|max:10';

        $validated = $request->validate($rules);

        $roomType = $this->service->createRoomType($validated);
        return response()->json($roomType, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $roomType = $this->service->findRoomType($id);
        $validated = $request->validate($this->rules);
        $roomType = $this->service->updateRoomType($roomType, $validated);
        return response()->json($roomType, 200);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->service->deleteRoomType($id);
        return response()->json(['message' => 'Room type deleted successfully'], 200);
    }
}
