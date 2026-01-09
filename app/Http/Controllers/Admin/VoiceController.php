<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\Voice;
use Illuminate\Http\Request;
use App\Helpers\ImageUploadHelper;
use Cloudinary\Cloudinary;
use Illuminate\Support\Facades\Validator;

class VoiceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $voices = Voice::all();
        return response()->json([
            'status' => true,
            'message' => 'Voices fetched successfully',
            'data' => $voices
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'nullable|email',
            'message' => 'required|string',
            'phone' => 'nullable|string',
            'photo' => 'nullable|image|max:2048',
            'role' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();
        $tenantDomain = tenant()->database;

        if ($request->hasFile('photo')) {
            $upload = ImageUploadHelper::uploadToCloud($request->file('photo'), "{$tenantDomain}/voices");
            if ($upload) {
                $data['photo'] = $upload['url'];
                $data['cloudinary_id'] = $upload['public_id'];
            }
        }

        $voice = Voice::create($data);
        return response()->json([
            'status' => true,
            'message' => 'Voice created successfully',
            'data' => $voice
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($domain, string $id)
    {
        $voice = Voice::findOrFail($id);
        return response()->json([
            'status' => true,
            'message' => 'Voice fetched successfully',
            'data' => $voice
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request,$domain,  string $id)
    {
        $voice = Voice::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'email' => 'nullable|email',
            'message' => 'sometimes|required|string',
            'phone' => 'nullable|string',
            'photo' => 'nullable|image|max:2048',
            'role' => 'seometimes|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();
        $tenantDomain = tenant()->database;

        if ($request->hasFile('photo')) {
            $upload = ImageUploadHelper::uploadToCloud($request->file('photo'), "{$tenantDomain}/voices", $voice->cloudinary_id);
            if ($upload) {
                $data['photo'] = $upload['url'];
                $data['cloudinary_id'] = $upload['public_id'];
            }
        }

        $voice->update($data);
        return response()->json([
            'status' => true,
            'message' => 'Voice updated successfully',
            'data' => $voice
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($domain, string $id)
    {
        $voice = Voice::findOrFail($id);
        
        if ($voice->cloudinary_id) {
            try {
                $cloudinary = new Cloudinary(env('CLOUDINARY_URL'));
                $cloudinary->uploadApi()->destroy($voice->cloudinary_id);
            } catch (\Exception $e) {
                \Log::error("Failed to delete voice image: " . $e->getMessage());
            }
        }

        $voice->delete();
        return response()->json([
            'status' => true,
            'message' => 'Voice deleted successfully'
        ]);
    }
}
