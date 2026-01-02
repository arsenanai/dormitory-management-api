<?php

namespace App\Services;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileService
{
    /**
     * Validate an uploaded file with given rules
     *
     * @param mixed $value The file to validate
     * @param array $rules Validation rules
     * @return array Validation result with 'valid' boolean and 'message' string
     */
    public function validateFile($value, array $rules = []): array
    {
        // Skip validation for string values (existing file paths)
        if (is_string($value)) {
            return ['valid' => true, 'message' => null];
        }

        if (!$value instanceof UploadedFile) {
            return ['valid' => true, 'message' => null];
        }

        // Default rules if none provided
        if (empty($rules)) {
            $rules = [
                'mimes:jpeg,jpg,png,pdf',
                'mimetypes:image/jpg,image/jpeg,image/png,application/pdf,application/octet-stream',
                'max:2048'
            ];
        }

        $validator = Validator::make(
            ['file' => $value],
            ['file' => $rules]
        );

        return [
            'valid' => $validator->passes(),
            'message' => $validator->fails() ? $validator->errors()->first('file') : null
        ];
    }

    /**
     * Download a student file with public access (for avatars)
     *
     * @param string $filename The filename to download
     * @return StreamedResponse|JsonResponse
     */
    public function downloadStudentFile(string $filename)
    {
        $path = "student_files/{$filename}";

        // Check if file exists
        if (!Storage::disk('local')->exists($path)) {
            return response()->json(['message' => 'File not found.'], 404);
        }

        // Only allow image files for public access
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (!in_array($extension, $allowedExtensions)) {
            return response()->json(['message' => 'Unauthorized file type.'], 403);
        }

        // Stream the file download
        return Storage::disk('local')->download($path);
    }

    /**
     * Download a file with authentication check
     *
     * @param string $path The file path
     * @return StreamedResponse|JsonResponse
     */
    public function downloadAuthenticatedFile(string $path)
    {
        // Check if file exists
        if (!Storage::disk('local')->exists($path)) {
            return response()->json(['message' => 'File not found.'], 404);
        }

        // Stream the file download
        return Storage::disk('local')->download($path);
    }

    /**
     * Log file information for debugging
     *
     * @param int $index File index
     * @param UploadedFile $file The uploaded file
     * @return void
     */
    public function logFileInfo(int $index, UploadedFile $file): void
    {
        \Log::info('Debugging student file ' . $index, [
            'original_name' => $file->getClientOriginalName(),
            'extension' => $file->getClientOriginalExtension(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'is_valid' => $file->isValid(),
        ]);
    }
}
