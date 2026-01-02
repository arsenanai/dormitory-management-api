<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\FileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileController extends Controller
{
    private $fileService;

    public function __construct(FileService $fileService)
    {
        $this->fileService = $fileService;
    }

    /**
     * Handle a request to download a protected file.
     *
     * @param string $path The path to the file within the storage disk.
     * @return StreamedResponse|\Illuminate\Http\JsonResponse
     */
    public function download(string $path)
    {
        $user = Auth::user();

        // Basic check: Does the file exist?
        if (!Storage::disk('local')->exists($path)) {
            return response()->json(['message' => 'File not found.'], 404);
        }

        // **IMPORTANT: Implement Authorization Logic Here**
        // For now, we'll just check if the user is authenticated.
        // In a real application, you should verify if the logged-in user
        // has permission to view this specific file.
        // For example: Is this the student's own file? Is the user an admin?
        if (!$user) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        // If authorized, stream the file download.
        // This is memory-efficient for large files.
        return Storage::disk('local')->download($path);
    }

    /**
     * Handle a request to download an avatar file (public access).
     *
     * @param string $filename The avatar filename.
     * @return StreamedResponse|\Illuminate\Http\JsonResponse
     */
    public function downloadAvatar(string $filename)
    {
        return $this->fileService->downloadStudentFile($filename);
    }
}
