<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SduIntegrationService
{
    protected $configurationService;

    public function __construct(ConfigurationService $configurationService)
    {
        $this->configurationService = $configurationService;
    }

    /**
     * Fetch student data from SDU API
     *
     * @param string $studentId
     * @return array|null
     */
    public function getStudentData(string $studentId): ?array
    {
        $settings = $this->configurationService->getSduSettings();

        if (empty($settings['sdu_enabled']) || empty($settings['sdu_api_url'])) {
            return null;
        }

        $url = rtrim($settings['sdu_api_url'], '/') . '/student/' . $studentId; // Assumption: API structure
        // If the API requires query params instead: ?student_id=... adjust accordingly.
        // For now, assuming RESTful resource style or similar.
        // Or maybe it's a search endpoint? Let's assume a direct fetch by ID for now.
        // If the user provided specific docs (like PDF), I might need to check them, but the user plan allowed assumption.

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $settings['sdu_api_secret'],
                'Accept'        => 'application/json',
            ])->timeout(5)->get($url);

            if ($response->successful()) {
                $data = $response->json();
                return $this->normalizeStudentData($data);
            }

            Log::warning('SDU API Warning: ' . $response->status() . ' - ' . $response->body());
        } catch (\Exception $e) {
            Log::error('SDU API Error: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Normalize API response to match our internal structure
     */
    protected function normalizeStudentData(array $data): array
    {
        // Adjust these keys based on actual SDU API response
        return [
            'first_name'      => $data['first_name'] ?? $data['name'] ?? null,
            'last_name'       => $data['last_name'] ?? $data['surname'] ?? null,
            'iin'             => $data['iin'] ?? null,
            'faculty'         => $data['faculty'] ?? null,
            'specialty'       => $data['specialty'] ?? $data['program'] ?? null,
            'enrollment_year' => $data['enrollment_year'] ?? $data['year_of_admission'] ?? null,
            'course'          => $data['course'] ?? null,
            'gender'          => isset($data['gender']) ? strtolower($data['gender']) : null, // Ensure lowercase for our enums
            // Add other fields as needed
        ];
    }
}
