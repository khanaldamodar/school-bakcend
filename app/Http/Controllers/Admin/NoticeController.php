<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\Notice;
use App\Services\TenantLogger;
use Illuminate\Http\Request;

class NoticeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index($domain)
    {
        $notices = Notice::all();

        if ($notices->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No notices found.'

            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Notices retrieved successfully',
            'data' => $notices
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, $domain)
    {
        $validatedData = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'notice_date' => 'nullable|date',
            'image' => 'nullable|image|max:2048', // Max 2MB
        ]);

        if ($request->hasFile('image')) {
            $imageData = \App\Helpers\ImageUploadHelper::uploadToCloud($request->file('image'), $domain . '/notices');
            if ($imageData) {
                $validatedData['image'] = $imageData['url'];
                $validatedData['cloudinary_id'] = $imageData['public_id'];
            }
        }

        $notice = Notice::create($validatedData);

        TenantLogger::logCreate('notices', "Notice created: {$notice->title}", [
            'id' => $notice->id,
            'title' => $notice->title
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Notice created successfully',
            'data' => $notice
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($domain, string $id)
    {
        $notice = Notice::find($id);
        if (!$notice) {
            return response()->json([
                'status' => false,
                'message' => 'Notice not found'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Notice retrieved successfully',
            'data' => $notice
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $domain, string $id)
    {
        $notice = Notice::find($id);
        if (!$notice) {
            return response()->json([
                'status' => false,
                'message' => 'Notice not found'
            ], 404);
        }

        $validatedData = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|required|string',
            'notice_date' => 'sometimes|nullable|date',
            'image' => 'sometimes|nullable|image|max:2048', // Max 2MB
        ]);

        if ($request->hasFile('image')) {
            $imageData = \App\Helpers\ImageUploadHelper::uploadToCloud(
                $request->file('image'), 
                $domain . '/notices', 
                $notice->cloudinary_id
            );
            
            if ($imageData) {
                $validatedData['image'] = $imageData['url'];
                $validatedData['cloudinary_id'] = $imageData['public_id'];
            }
        }

        $notice->update($validatedData);

        TenantLogger::logUpdate('notices', "Notice updated: {$notice->title}", [
            'id' => $notice->id,
            'title' => $notice->title
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Notice updated successfully',
            'data' => $notice
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($domain,  string $id)
    {
        $notice = Notice::find($id);
        if (!$notice) {
            return response()->json([
                'status' => false,
                'message' => 'Notice not found'
            ], 404);
        }

        // Delete image from Cloudinary if exists
        if ($notice->cloudinary_id) {
            try {
                $cloudinary = new \Cloudinary\Cloudinary(env('CLOUDINARY_URL'));
                $cloudinary->uploadApi()->destroy($notice->cloudinary_id);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Cloudinary delete failed: ' . $e->getMessage());
            }
        }

        $notice->delete();

        TenantLogger::logDelete('notices', "Notice deleted: {$notice->title}", [
            'id' => $id,
            'title' => $notice->title
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Notice deleted successfully'
        ], 200);
    }
}
