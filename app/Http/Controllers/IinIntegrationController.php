<?php

namespace App\Http\Controllers;

use App\Services\IinIntegrationService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class IinIntegrationController extends Controller {
	public function __construct( private IinIntegrationService $iinIntegrationService ) {
	}

	/**
	 * Send OTP to student
	 */
	public function sendOtp( Request $request ) {
		$validated = $request->validate( [
			'student_id' => 'required|string',
		] );

		try {
			$response = $this->iinIntegrationService->sendOtp( $validated['student_id'] );

			return response()->json( $response );
		} catch (Exception $e) {
			Log::error( 'Send OTP Error: ' . $e->getMessage() );

			return response()->json( [ 'message' => $e->getMessage() ], 500 );
		}
	}

	/**
	 * Verify OTP and return decrypted data
	 */
	public function verifyOtp( Request $request ) {
		$validated = $request->validate( [
			'student_id' => 'required|string',
			'otp'        => 'required|string',
		] );

		try {
			$decryptedData = $this->iinIntegrationService->verifyOtp(
				$validated['student_id'],
				$validated['otp']
			);

			// Map the response to frontend structure
			$mappedData = [
				'firstName'      => $decryptedData['name'] ?? '',
				'lastName'       => $decryptedData['surname'] ?? '',
				'iin'            => $decryptedData['iin'] ?? '',
				'passportNumber' => $decryptedData['passport_number'] ?? '',
				'studentId'      => $decryptedData['student_id'] ?? '',
				'photoPath'      => $decryptedData['photo_path'] ?? null,
			];

			return response()->json( $mappedData );
		} catch (Exception $e) {
			Log::error( 'Verify OTP Error: ' . $e->getMessage() );

			return response()->json( [ 'message' => $e->getMessage() ], 500 );
		}
	}
}
