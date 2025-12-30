<?php

namespace App\Http\Controllers;

use App\Models\City;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CityController extends Controller
{
    /**
     * Display a listing of cities
     */
    public function index(Request $request): JsonResponse
    {
        $query = City::query();

        if ($request->has('region_id')) {
            $query->where('region_id', $request->region_id);
        }

        $cities = $query->with(['region', 'region.country'])->get();

        return response()->json([
            'success' => true,
            'data' => $cities
        ]);
    }

    /**
     * Store a newly created city
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'region_id' => 'required|exists:regions,id'
        ]);

        $city = City::create($validated);

        return response()->json([
            'success' => true,
            'data' => $city->load(['region', 'region.country'])
        ], 201);
    }

    /**
     * Display the specified city
     */
    public function show(City $city): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $city->load(['region', 'region.country'])
        ]);
    }

    /**
     * Update the specified city
     */
    public function update(Request $request, City $city): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'region_id' => 'sometimes|exists:regions,id'
        ]);

        $city->update($validated);

        return response()->json([
            'success' => true,
            'data' => $city->load(['region', 'region.country'])
        ]);
    }

    /**
     * Remove the specified city
     */
    public function destroy(City $city): JsonResponse
    {
        $city->delete();

        return response()->json([
            'success' => true,
            'message' => 'City deleted successfully'
        ]);
    }
}
