<?php

namespace App\Services;

use App\Models\RoomType;

class RoomTypeService
{
    private FileService $fileService;

    public function __construct(FileService $fileService)
    {
        $this->fileService = $fileService;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Models\RoomType>
     */
    public function listRoomTypes(): \Illuminate\Database\Eloquent\Collection
    {
        return RoomType::all();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return \App\Models\RoomType
     */
    public function createRoomType(array $data): \App\Models\RoomType
    {
        $data['photos'] = $this->handlePhotoUploads($data);
        return RoomType::create($data);
    }

    /**
     * @param  int|string  $id
     * @return \App\Models\RoomType
     */
    public function findRoomType($id): \App\Models\RoomType
    {
        return RoomType::findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return \App\Models\RoomType
     */
    public function updateRoomType(\App\Models\RoomType $roomType, array $data): \App\Models\RoomType
    {
        $data['photos'] = $this->handlePhotoUploads($data, $roomType);
        $roomType->update($data);
        return $roomType;
    }

    /**
     * @param  int|string  $id
     */
    public function deleteRoomType($id): void
    {
        $roomType = RoomType::findOrFail($id);

        // Clean up associated files
        $this->deletePhotos($roomType->photos ?? []);

        $roomType->delete();
    }

    private function handlePhotoUploads(array $data, ?RoomType $existingRoomType = null): array
    {
        $newPhotoPaths = [];
        // 1. Upload new photos if any are present in the request
        if (isset($data['photos']) && is_array($data['photos'])) {
            $newPhotoPaths = $this->fileService->uploadMultipleFiles($data['photos'], 'room-type', 10);
        }

        // 2. Get the list of existing photos from the request (for updates) — ensure array
        $existingPhotos = $this->normalizePhotosToArray($data['existing_photos'] ?? null);

        // 3. If it's an update, determine which old photos to delete
        if ($existingRoomType !== null) {
            $originalPhotos = $this->normalizePhotosToArray($existingRoomType->photos);
            $photosToDelete = array_diff($originalPhotos, $existingPhotos);
            $this->deletePhotos($photosToDelete);
        }

        // 4. Merge existing and new photos to create the final list
        return array_merge($existingPhotos, $newPhotoPaths);
    }

    /**
     * Ensure value is an array of photo paths (handles JSON string or raw string from DB).
     *
     * @param  mixed  $value  photos from request (JSON string) or model (array|string)
     * @return array<int, string>
     */
    private function normalizePhotosToArray(mixed $value): array
    {
        if (is_array($value)) {
            return array_values($value);
        }
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? array_values($decoded) : [];
        }
        return [];
    }

    private function deletePhotos(array $photos)
    {
        if (!empty($photos)) {
            $this->fileService->deleteMultipleFiles($photos);
        }
    }
}
