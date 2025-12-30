<?php

namespace App\Services;

use App\Models\RoomType;
use Illuminate\Support\Facades\Storage;

class RoomTypeService
{
    public function listRoomTypes()
    {
        return RoomType::all();
    }

    public function createRoomType(array $data)
    {
        return RoomType::create($data);
    }

    public function findRoomType($id)
    {
        return RoomType::findOrFail($id);
    }

    public function updateRoomType(RoomType $roomType, array $data)
    {
        // Handle photo cleanup if new photos are provided
        if (isset($data['photos']) && $roomType->photos) {
            $photos = is_string($roomType->photos) ? json_decode($roomType->photos, true) : $roomType->photos;
            $this->deletePhotos($photos ?? []);
        }

        $roomType->update($data);
        return $roomType;
    }

    public function deleteRoomType($id)
    {
        $roomType = RoomType::findOrFail($id);

        // Clean up associated files
        if ($roomType->minimap) {
            Storage::disk('public')->delete($roomType->minimap);
        }

        if ($roomType->photos) {
            $photos = is_string($roomType->photos) ? json_decode($roomType->photos, true) : $roomType->photos;
            $this->deletePhotos($photos ?? []);
        }

        $roomType->delete();
    }

    private function deletePhotos(array $photos)
    {
        foreach ($photos as $photo) {
            Storage::disk('public')->delete($photo);
        }
    }
}
