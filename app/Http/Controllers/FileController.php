<?php

namespace App\Http\Controllers;

use App\Services\FileService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileController extends Controller
{
    public function __construct(
        private readonly FileService $fileService,
    ) {
    }

    public function download(string $path): StreamedResponse|JsonResponse
    {
        return $this->fileService->downloadFile($path);
    }
}
