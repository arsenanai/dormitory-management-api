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
     * Download a file with public access
     *
     * @param string $path The file path
     * @return StreamedResponse|JsonResponse
     */
    public function downloadFile(string $path)
    {
        // Check if file exists
        if (!Storage::disk('public')->exists($path)) {
            return response()->json(['message' => 'File not found.'], 404);
        }

        // Stream the file download
        return Storage::disk('public')->download($path);
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

    /**
     * Upload multiple files to a specific folder
     *
     * @param array $files Array of UploadedFile objects
     * @param string $folder Target folder (e.g., 'room-type', 'student-profile', 'avatar')
     * @param int|null $limit Maximum number of files to upload
     * @return array Array of file paths
     */
    public function uploadMultipleFiles(array $files, string $folder, ?int $limit = null): array
    {
        $paths = [];
        $count = 0;

        foreach ($files as $file) {
            if ($limit && $count >= $limit) {
                break;
            }

            if ($file instanceof UploadedFile && $file->isValid()) {
                $path = $file->store($folder, 'public');
                if ($path) {
                    $paths[] = $path;
                    $count++;
                }
            }
        }

        return $paths;
    }

    /**
     * Delete multiple files from storage
     *
     * @param array $filePaths Array of file paths to delete
     * @return void
     */
    public function deleteMultipleFiles(array $filePaths): void
    {
        foreach ($filePaths as $filePath) {
            if ($filePath && is_string($filePath)) {
                Storage::disk('public')->delete($filePath);
            }
        }
    }

    /**
     * Replace files with cleanup of old files
     *
     * @param array $oldFilePaths Array of old file paths to delete
     * @param array $newFiles Array of new UploadedFile objects
     * @param string $folder Target folder for new files
     * @param int|null $limit Maximum number of files to upload
     * @return array Array of new file paths
     */
    public function replaceFilesWithCleanup(array $oldFilePaths, array $newFiles, string $folder, ?int $limit = null): array
    {
        // Delete old files first
        $this->deleteMultipleFiles($oldFilePaths);

        // Upload new files
        return $this->uploadMultipleFiles($newFiles, $folder, $limit);
    }

    /**
     * Validate image file specifically
     *
     * @param mixed $file The file to validate
     * @param int|null $maxSizeKB Maximum file size in KB
     * @return array Validation result with 'valid' boolean and 'message' string
     */
    public function validateImageFile($file, ?int $maxSizeKB = null): array
    {
        // Skip validation for string values (existing file paths)
        if (is_string($file)) {
            return ['valid' => true, 'message' => null];
        }

        if (!$file instanceof UploadedFile) {
            return ['valid' => false, 'message' => 'Invalid file format.'];
        }

        // Build validation rules
        $rules = ['mimes:jpeg,jpg,png,webp', 'image'];

        if ($maxSizeKB) {
            $rules[] = "max:{$maxSizeKB}";
        } else {
            $rules[] = 'max:5120'; // Default 5MB
        }

        $validator = Validator::make(
            ['file' => $file],
            ['file' => $rules]
        );

        return [
            'valid' => $validator->passes(),
            'message' => $validator->fails() ? $validator->errors()->first('file') : null
        ];
    }
}
