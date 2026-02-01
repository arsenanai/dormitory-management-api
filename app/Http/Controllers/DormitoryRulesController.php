<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\DormitoryRulesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DormitoryRulesController extends Controller
{
    public function __construct(
        private readonly DormitoryRulesService $dormitoryRulesService,
    ) {
    }

    /**
     * Get dormitory rules for all locales.
     */
    public function index(): JsonResponse
    {
        $locales = $this->dormitoryRulesService->getLocales();
        return response()->json([ 'locales' => $locales ]);
    }

    /**
     * Update dormitory rules for all locales.
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'locales'         => 'required|array',
            'locales.en'      => 'nullable|string',
            'locales.kk'      => 'nullable|string',
            'locales.ru'      => 'nullable|string',
        ]);

        $this->dormitoryRulesService->updateLocales($validated['locales']);

        return response()->json([ 'message' => 'Dormitory rules updated successfully.' ]);
    }
}
