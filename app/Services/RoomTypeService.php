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
    public function listRoomTypes()
    {
        return RoomType::all();
    }

    public function createRoomType(array $data)
    {
        $data['photos'] = $this->handlePhotoUploads($data);
        return RoomType::create($data);
    }

    public function findRoomType($id)
    {
        return RoomType::findOrFail($id);
    }

    public function updateRoomType(RoomType $roomType, array $data)
    {
        $data['photos'] = $this->handlePhotoUploads($data, $roomType);
        $roomType->update($data);
        return $roomType;
    }

    public function deleteRoomType($id)
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

        // 2. Get the list of existing photos from the request (for updates)
        $existingPhotos = isset($data['existing_photos']) ? json_decode($data['existing_photos'], true) : [];

        // 3. If it's an update, determine which old photos to delete
        if ($existingRoomType) {
            $originalPhotos = $existingRoomType->photos ?? [];
            $photosToDelete = array_diff($originalPhotos, $existingPhotos);
            $this->deletePhotos($photosToDelete);
        }

        // 4. Merge existing and new photos to create the final list
        return array_merge($existingPhotos, $newPhotoPaths);
    }

    private function deletePhotos(array $photos)
    {
        if (!empty($photos)) {
            $this->fileService->deleteMultipleFiles($photos);
        }
    }
}
