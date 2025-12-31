<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\ImageUploadHelper;
use App\Http\Controllers\Controller;
use App\Models\Admin\SchoolMember;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Exception;
use App\Services\TenantLogger;

class SchoolMemberController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index($domain)
    {
        try {
            $members = SchoolMember::orderBy('id', 'desc')->get();

            return response()->json([
                'status' => true,
                'message' => 'School members fetched successfully.',
                'data' => $members
            ]);

        } catch (Exception $e) {
            return $this->errorResponse($e);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, $domain)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'email' => 'nullable|email',
                'phone' => 'nullable|string|max:20',
                'gender' => 'nullable|in:male,female,other',
                'caste' => 'nullable|in:brahmin,chhetri,janajati,dalit,muslim,other',
                'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:3072',
                'disability_options' => 'nullable|in:none,visual,hearing,physical,mental,other',
            ]);

            if ($validator->fails()) {
                return $this->validationError($validator->errors());
            }

            $data = $request->except('image');

            // Upload to Cloudinary
            if ($request->hasFile('image')) {
                $upload = ImageUploadHelper::uploadToCloud(
                    $request->file('image'),
                    'school_members'
                );

                $data['image'] = $upload['url'];
                $data['image_public_id'] = $upload['public_id'];
            }

            $member = SchoolMember::create($data);

            TenantLogger::logCreate('school_members', "School member created: {$member->name}", [
                'id' => $member->id,
                'name' => $member->name
            ]);

            return response()->json([
                'status' => true,
                'message' => 'School member created successfully.',
                'data' => $member
            ]);

        } catch (Exception $e) {
            return $this->errorResponse($e);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $domain, $id)
    {
        try {
            $member = SchoolMember::find($id);

            if (!$member) {
                return $this->notFound();
            }

            return response()->json([
                'status' => true,
                'message' => 'School member fetched.',
                'data' => $member
            ]);

        } catch (Exception $e) {
            return $this->errorResponse($e);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $domain, $id)
    {
        try {
            $member = SchoolMember::find($id);

            if (!$member) {
                return $this->notFound();
            }

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|string|max:255',
                'email' => 'nullable|email',
                'phone' => 'nullable|string|max:20',
                'gender' => 'nullable|in:male,female,other',
                'caste' => 'nullable|in:brahmin,chhetri,janajati,dalit,muslim,other',
                'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:3072',
                'disability_options' => 'nullable|in:none,visual,hearing,physical,mental,other',
            ]);

            if ($validator->fails()) {
                return $this->validationError($validator->errors());
            }

            $data = $request->except('image');

            if ($request->hasFile('image')) {

                // Upload new image replacing old one
                $upload = ImageUploadHelper::uploadToCloud(
                    $request->file('image'),
                    'school_members',
                    $member->image_public_id
                );

                $data['image'] = $upload['url'];
                $data['image_public_id'] = $upload['public_id'];
            }

            $member->update($data);

            TenantLogger::logUpdate('school_members', "School member updated: {$member->name}", [
                'id' => $member->id
            ]);

            return response()->json([
                'status' => true,
                'message' => 'School member updated successfully.',
                'data' => $member
            ]);

        } catch (Exception $e) {
            return $this->errorResponse($e);
        }
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $domain, $id)
    {
        try {
            $member = SchoolMember::find($id);

            if (!$member) {
                return $this->notFound();
            }

            if ($member->image_public_id) {
                // delete from cloudinary
                $cloud = new \Cloudinary\Cloudinary(env('CLOUDINARY_URL'));
                $cloud->uploadApi()->destroy($member->image_public_id);
            }

            $member->delete();

            TenantLogger::logDelete('school_members', "School member deleted: {$member->name}", [
                'id' => $id,
                'name' => $member->name
            ]);

            return response()->json([
                'status' => true,
                'message' => 'School member deleted successfully.'
            ]);

        } catch (Exception $e) {
            return $this->errorResponse($e);
        }
    }
    private function errorResponse($e)
    {
        return response()->json([
            'status' => false,
            'message' => 'Something went wrong!',
            'error' => $e->getMessage()
        ], 500);
    }

    private function notFound()
    {
        return response()->json([
            'status' => false,
            'message' => 'School member not found.'
        ], 404);
    }

    private function validationError($errors)
    {
        return response()->json([
            'status' => false,
            'message' => 'Validation failed.',
            'errors' => $errors
        ], 422);
    }
}