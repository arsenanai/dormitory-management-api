<?php

namespace App\Http\Controllers;

use App\Services\FileService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileController extends Controller
{
    private $fileService;

    public function __construct(FileService $fileService)
    {
        $this->fileService = $fileService;
    }

    /**
     * Handle a request to download a file (public access).
     *
     * @param string $path The path to file within storage disk.
     * @return StreamedResponse|JsonResponse
     */
    public function download(string $path)
    {
        return $this->fileService->downloadFile($path);
    }
}
