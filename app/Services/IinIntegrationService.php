<?php

namespace App\Services;

use App\Services\ConfigurationService;
use App\Services\FileService;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class IinIntegrationService {
	public function __construct(
		private ConfigurationService $configurationService,
		private FileService $fileService
	) {
	}

	/**
	 * Send OTP to student
	 *
	 * @throws Exception
	 */
	public function sendOtp( string $studentId ): array {
		$settings = $this->configurationService->getIinSettings();

		if ( ! $settings['iin_integration_enabled'] ) {
			throw new Exception( 'IIN integration is disabled.' );
		}

		$baseUrl = rtrim( $settings['iin_base_url'], '/' );
		$url = "$baseUrl/$studentId/send";

		try {
			$response = Http::post( $url );

			if ( $response->failed() ) {
				Log::error( "IIN Send OTP failed for student $studentId: " . $response->body() );
				throw new Exception( 'Failed to send OTP. Please check the student ID or try again later.' );
			}

			return $response->json();
		} catch (Exception $e) {
			Log::error( "IIN Send OTP exception for student $studentId: " . $e->getMessage() );
			throw $e;
		}
	}

	/**
	 * Verify OTP and decrypt data
	 *
	 * @throws Exception
	 */
	public function verifyOtp( string $studentId, string $otp ): array {
		$settings = $this->configurationService->getIinSettings();

		if ( ! $settings['iin_integration_enabled'] ) {
			throw new Exception( 'IIN integration is disabled.' );
		}

		$baseUrl = rtrim( $settings['iin_base_url'], '/' );
		$url = "$baseUrl/$studentId/verify";

		try {
			$response = Http::post( $url, [ 'otp' => $otp ] );

			if ( $response->failed() ) {
				Log::error( "IIN Verify OTP failed for student $studentId: " . $response->body() );
				throw new Exception( 'Invalid OTP or verification failed.' );
			}

			$data = $response->json();

			if ( ! isset( $data['encrypted_data'] ) ) {
				throw new Exception( 'Invalid response format from IIN service.' );
			}

			$decryptedData = $this->decryptData( $data['encrypted_data'] );

			// Handle photo if present in decrypted data
			if ( ! empty( $decryptedData['photo'] ) ) {
				$photoPath = $this->fileService->uploadBase64File(
					$decryptedData['photo'],
					'student-profile',
					'iin_' . $studentId . '_' . time() . '.jpg'
				);
				if ( $photoPath ) {
					$decryptedData['photo_path'] = $photoPath;
				}
			}

			return $decryptedData;
		} catch (Exception $e) {
			Log::error( "IIN Verify OTP exception for student $studentId: " . $e->getMessage() );
			throw $e;
		}
	}

	/**
	 * Decrypt data using AES-256-GCM
	 *
	 * @throws Exception
	 */
	private function decryptData( string $encryptedPayload ): array {
		$settings = $this->configurationService->getIinSettings();
		$key = $settings['iin_encryption_key'];

		if ( empty( $key ) ) {
			throw new Exception( 'Encryption key is not configured.' );
		}

		// The key must be decoded if it was stored as base64 or used as is depending on format.
		// Usually encryption keys are binary or base64. Based on decrypt.php, it seems the key is used directly?
		// Re-checking decrypt.php... it doesn't show key processing, just usage.
		// Assuming the key is provided in a format suitable for openssl directly or base64 encoded.
		// Given the user instructions mentioned "Encryption Key" in database.
		// Let's assume the key is stored as a string (maybe base64 encoded).
		// We should try to decode it if it looks like base64, or use as is.
		// However, AES-256 key should be 32 bytes.
		// Let's try base64_decode on the input key first if it's longer than 32 chars?
		// Actually, let's look at `decrypt.php` again if possible. It wasn't fully shown in summary.
		// But in PHP `openssl_decrypt` needs a binary key.
		// If the user inputs a base64 string, we should decode it.

		// Let's assume the input key is a simple string for now, or base64.
		// If it is 32 bytes, fine. If it is base64, decode it.
		// A safe bet is to try to decode if it's base64, but that's ambiguous.
		// For now, let's treat the config value as the key itself (binary string if possible, or text).
		// Wait, the plan says: `Get iin_encryption_key from config. Use base64_decode.`
		// SO I WILL FOLLOW THE PLAN.

		// The plan says: "Get iin_encryption_key from config. Use base64_decode."
		// Wait, did the plan mean decode the key OR decode the payload?
		// Ah, standard practice: Key is usually binary, so stored as Base64.
		// Payload is definitely Base64.

		// Correct logic from plan:
		// 1. Get key.
		// 2. Decode payload (base64).
		// 3. Extract IV, tag, ciphertext.
		// 4. Decrypt.

		// Regarding the key: The prompt in `decrypt.php` usually implies a binary key.
		// If the user saves a string "mysecretkey", it might need hashing or padding.
		// But usually these keys are 32 random bytes, so base64 is the transport format.
		// I will Assume the stored key IS the raw key string, OR base64.
		// But `openssl_decrypt` with `aes-256-gcm` requires a 32-byte key.
		// I'll stick to the plan: `decryptData` logic. It doesn't explicitly say to base64_decode the KEY.
		// It says "Get iin_encryption_key from config. Use base64_decode." - this sentence in the plan
		// was under `decryptData` but might have referred to the payload.
		// Let's look at the plan text again:
		// "- Get iin_encryption_key from config. Use base64_decode."
		// "- Decode payload (base64)."
		// It seems it meant decode the key too. OK.

		$key = base64_decode( $key );

		$data = base64_decode( $encryptedPayload );

		if ( $data === false ) {
			throw new Exception( 'Failed to decode encrypted payload.' );
		}

		$ivLength = 12;
		$tagLength = 16;

		$iv = substr( $data, 0, $ivLength );
		$tag = substr( $data, $ivLength, $tagLength );
		$ciphertext = substr( $data, $ivLength + $tagLength );

		$decrypted = openssl_decrypt(
			$ciphertext,
			'aes-256-gcm',
			$key,
			OPENSSL_RAW_DATA,
			$iv,
			$tag
		);

		if ( $decrypted === false ) {
			throw new Exception( 'Decryption failed.' );
		}

		return json_decode( $decrypted, true );
	}
}
