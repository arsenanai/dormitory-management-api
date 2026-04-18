<?php

namespace App\Http\Controllers;

use App\Services\IinIntegrationService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class IinIntegrationController extends Controller
{
    public function __construct(private IinIntegrationService $iinIntegrationService)
    {
    }

    /**
     * Send OTP to student
     */
    public function sendOtp(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'student_id' => 'required|string',
        ]);

        try {
            $response = $this->iinIntegrationService->sendOtp($validated['student_id']);

            return response()->json([
                'message'    => $response['message'] ?? 'OTP sent',
                'identifier' => $response['masked_email'] ?? null,
            ]);
        } catch (Exception $e) {
            Log::error('Send OTP Error: ' . $e->getMessage());

            $body = [ 'message' => $e->getMessage() ];
            if ($this->isDebugEnvironment()) {
                $body['debug'] = $this->iinIntegrationService->getLastRequestDebug();
            }

            return response()->json($body, 500);
        }
    }

    /**
     * Verify OTP and return decrypted data
     */
    public function verifyOtp(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'student_id' => 'required|string',
            'otp'        => 'required|string',
        ]);

        try {
            $decryptedData = $this->iinIntegrationService->verifyOtp(
                $validated['student_id'],
                $validated['otp']
            );

            // Map the SDU API response fields to frontend structure
            $mappedData = [
                'firstName'      => $decryptedData['first_name'] ?? '',
                'lastName'       => $decryptedData['last_name'] ?? '',
                'iin'            => $decryptedData['iin_no'] ?? '',
                'passportNumber' => $decryptedData['passport_no'] ?? '',
                'studentId'      => $decryptedData['stud_id'] ?? '',
                'photoPath'      => $decryptedData['photo_path'] ?? null,
                'gender'         => $decryptedData['gender'] ?? '',
                'email'          => $decryptedData['email'] ?? '',
                'phones'         => $decryptedData['phones'] ?? [],
                'country'        => $decryptedData['address']['country'] ?? '',
                'region'         => $decryptedData['address']['province'] ?? '',
                'city'           => $decryptedData['address']['city'] ?? '',
                'specialist'     => $decryptedData['speciality'] ?? '',
                'enrollmentYear' => $decryptedData['enrollment_year'] ?? '',
                'emergencyContactName'  => $decryptedData['emergency_contact'][0]['name'] ?? '',
                'emergencyContactPhone' => $decryptedData['emergency_contact'][0]['phone'] ?? '',
                'emergencyContactType'  => $decryptedData['emergency_contact'][0]['emergency_contact_type'] ?? '',
                'emergencyContactEmail' => $decryptedData['emergency_contact'][0]['email'] ?? '',
            ];

            return response()->json($mappedData);
        } catch (Exception $e) {
            Log::error('Verify OTP Error: ' . $e->getMessage());

            $body = [ 'message' => $e->getMessage() ];
            if ($this->isDebugEnvironment()) {
                $body['debug'] = $this->iinIntegrationService->getLastRequestDebug();
            }

            return response()->json($body, 500);
        }
    }

    private function isDebugEnvironment(): bool
    {
        return in_array(config('app.env'), [ 'local', 'development', 'staging' ]);
    }
}
