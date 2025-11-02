<?php

namespace App\Helpers;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Exception;
use Cloudinary\Cloudinary;

class ImageUploadHelper
{
    /**
     * Upload an image to Cloudinary within a specific folder and return URL + public_id.
     *
     * @param \Illuminate\Http\UploadedFile|null $image
     * @param string $folderPath
     * @param string|null $oldPublicId
     * @return array|null
     * @throws \Exception
     */
    public static function uploadToCloud($image, string $folderPath, string $oldPublicId = null)
    {
        if (!$image instanceof UploadedFile) {
            return null;
        }

        try {
            // Delete old image if provided
            if ($oldPublicId) {
                $cloudinary = new Cloudinary(env('CLOUDINARY_URL'));
                $cloudinary->uploadApi()->destroy($oldPublicId);
            }

            // Generate unique filename
            $filename = uniqid() . '_' . $image->getClientOriginalName();

            // Upload via Cloudinary SDK
            $cloudinary = new Cloudinary(env('CLOUDINARY_URL'));
            $uploadResult = $cloudinary->uploadApi()->upload(
                $image->getRealPath(),
                [
                    'folder' => $folderPath,
                    'public_id' => pathinfo($filename, PATHINFO_FILENAME),
                    'overwrite' => true,
                ]
            );

            return [
                'url' => $uploadResult['secure_url'],
                'public_id' => $uploadResult['public_id'],
            ];
        } catch (Exception $e) {
            throw new Exception('Image upload failed: ' . $e->getMessage());
        }
    }
}
