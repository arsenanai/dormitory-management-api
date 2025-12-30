<?php

namespace App\Http\Controllers;

use App\Models\Region;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RegionController extends Controller
{
    /**
     * Display a listing of regions
     */
    public function index(Request $request): JsonResponse
    {
        $query = Region::query();

        if ($request->has('country_id')) {
            $query->where('country_id', $request->country_id);
        }

        $regions = $query->with('country')->get();

        return response()->json([
            'success' => true,
            'data' => $regions
        ]);
    }

    /**
     * Store a newly created region
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'country_id' => 'required|exists:countries,id'
        ]);

        $region = Region::create($validated);

        return response()->json([
            'success' => true,
            'data' => $region->load('country')
        ], 201);
    }

    /**
     * Display the specified region
     */
    public function show(Region $region): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $region->load('country')
        ]);
    }

    /**
     * Update the specified region
     */
    public function update(Request $request, Region $region): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'country_id' => 'sometimes|exists:countries,id'
        ]);

        $region->update($validated);

        return response()->json([
            'success' => true,
            'data' => $region->load('country')
        ]);
    }

    /**
     * Remove the specified region
     */
    public function destroy(Region $region): JsonResponse
    {
        $region->delete();

        return response()->json([
            'success' => true,
            'message' => 'Region deleted successfully'
        ]);
    }
}
