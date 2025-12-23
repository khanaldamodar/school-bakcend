<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\ImageUploadHelper;
use App\Http\Controllers\Controller;
use App\Models\Admin\Gallery;
use Illuminate\Http\Request;

class GalleryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index($domain)
    {
        $gallery = Gallery::all();

        if ($gallery->isEmpty()) {
            return response()->json(['message' => 'No gallery found'], 404);
        }

        return response()->json(['data' => $gallery], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, $domain)
    {
        $validatedData = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'content_type' => 'required|in:image,video,mixed',
            'for' => 'nullable|string|max:255',

            // media must be uploaded files
            'media' => 'nullable|array',
            'media.*' => 'file|mimes:jpg,jpeg,png,mp4,mov,avi|max:40960', // 40MB max
        ]);

        $uploadedMedia = [];

        if ($request->hasFile('media')) {
            foreach ($request->file('media') as $file) {
                $upload = ImageUploadHelper::uploadToCloud($file, 'school/gallery');

                if ($upload) {
                    $uploadedMedia[] = [
                        'url' => $upload['url'],
                        'public_id' => $upload['public_id'],
                        'type' => $file->getClientMimeType(),
                    ];
                }
            }
        }

        // Save gallery with media
        $gallery = Gallery::create([
            'title' => $validatedData['title'],
            'description' => $validatedData['description'] ?? null,
            'content_type' => $validatedData['content_type'],
            'for' => $validatedData['for'] ?? null,
            'media' => $uploadedMedia,  // Store JSON array
        ]);

        return response()->json([
            'message' => 'Gallery created successfully',
            'data' => $gallery
        ], 201);
    }


    /**
     * Display the specified resource.
     */
    public function show(string $domain, string $id)
    {
        $gallery = Gallery::find($id);

        if (!$gallery) {
            return response()->json(['message' => 'Gallery not found'], 404);
        }

        return response()->json(['data' => $gallery], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $domain, string $id)
    {
        $gallery = Gallery::find($id);

        if (!$gallery) {
            return response()->json(['message' => 'Gallery not found'], 404);
        }

        $validatedData = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'content_type' => 'sometimes|required|in:image,video,mixed',
            'for' => 'nullable|string|max:255',

            // uploading new media
            'media' => 'nullable|array',
            'media.*' => 'file|mimes:jpg,jpeg,png,mp4,mov,avi|max:40960',

            // removing existing media
            'remove_media' => 'nullable|array',
            'remove_media.*' => 'string', // public_id of media to remove
        ]);

        // Start with old media
        $finalMedia = $gallery->media ?? [];

        /**
         * --------------------------------------------
         * DELETE SELECTED OLD MEDIA FROM CLOUDINARY
         * --------------------------------------------
         */
        if ($request->has('remove_media')) {
            foreach ($request->remove_media as $publicIdToRemove) {

                // Remove from Cloudinary
                try {
                    $cloudinary = new \Cloudinary\Cloudinary(env('CLOUDINARY_URL'));
                    $cloudinary->uploadApi()->destroy($publicIdToRemove);
                } catch (\Exception $e) {
                    // Continue even if deletion fails
                }

                // Remove from DB media array
                $finalMedia = array_filter($finalMedia, function ($mediaItem) use ($publicIdToRemove) {
                    return $mediaItem['public_id'] !== $publicIdToRemove;
                });
            }
            // Reset array keys
            $finalMedia = array_values($finalMedia);
        }

        /**
         * --------------------------------------------
         * UPLOAD NEW MEDIA FILES
         * --------------------------------------------
         */
        if ($request->hasFile('media')) {
            foreach ($request->file('media') as $file) {
                $upload = \App\Helpers\ImageUploadHelper::uploadToCloud($file, 'school/gallery');

                if ($upload) {
                    $finalMedia[] = [
                        'url' => $upload['url'],
                        'public_id' => $upload['public_id'],
                        'type' => $file->getClientMimeType(),
                    ];
                }
            }
        }

        /**
         * --------------------------------------------
         * UPDATE GALLERY RECORD
         * --------------------------------------------
         */
        $gallery->update([
            'title' => $validatedData['title'] ?? $gallery->title,
            'description' => $validatedData['description'] ?? $gallery->description,
            'content_type' => $validatedData['content_type'] ?? $gallery->content_type,
            'for' => $validatedData['for'] ?? $gallery->for,
            'media' => $finalMedia,
        ]);

        return response()->json([
            'message' => 'Gallery updated successfully',
            'data' => $gallery
        ], 200);
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $domain, string $id)
    {
        $gallery = Gallery::find($id);

        if (!$gallery) {
            return response()->json(['message' => 'Gallery not found'], 404);
        }

        $gallery->delete();

        return response()->json(['message' => 'Gallery deleted successfully'], 200);
    }
}
