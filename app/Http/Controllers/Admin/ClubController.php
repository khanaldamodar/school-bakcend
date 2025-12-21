<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\Club;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

use App\Helpers\ImageUploadHelper;
use Cloudinary\Cloudinary;

class ClubController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $clubs = Club::all();

        if ($clubs->isEmpty()) {
            return response()->json([
                'status' => false,
                "Message" => "No clubs found",
            ], 404);
        }


        return response()->json([
            "message" => "Clubs Fetched Successfully",
            "clubs" => $clubs,
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'logo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'description' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $data = $validator->validated();

            // Upload logo if exists
            if ($request->hasFile('logo')) {
                $upload = ImageUploadHelper::uploadToCloud(
                    $request->file('logo'),
                    'clubs/logos'
                );

                $data['logo'] = $upload['url'];
            }

            $club = Club::create($data);

            return response()->json([
                'status' => true,
                'message' => 'Club created successfully',
                'club' => $club
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Club creation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $domain, string $id)
    {
        $club = Club::findOrFail($id);


        if (!$club) {
            return response()->json([
                "status" => false,
                "Message" => "This Club is not Found"
            ], 404);
        }


        return response()->json([
            "status" => true,
            "message" => "Club Found Successfully",
            "club" => $club
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $domain, string $id)
    {
        $club = Club::find($id);

        if (!$club) {
            return response()->json([
                'status' => false,
                'message' => 'Club not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'logo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'description' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $data = $validator->validated();

            // Replace logo if new one uploaded
            if ($request->hasFile('logo')) {

                // Extract old public_id from URL (important)
                $oldPublicId = null;
                if ($club->logo) {
                    $oldPublicId = pathinfo(parse_url($club->logo, PHP_URL_PATH), PATHINFO_FILENAME);
                }

                $upload = ImageUploadHelper::uploadToCloud(
                    $request->file('logo'),
                    'clubs/logos',
                    $oldPublicId
                );

                $data['logo'] = $upload['url'];
            }

            $club->update($data);

            return response()->json([
                'status' => true,
                'message' => 'Club updated successfully',
                'club' => $club
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Club update failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $domain, string $id)
    {
        $club = Club::find($id);

        if (!$club) {
            return response()->json([
                'status' => false,
                'message' => 'Club not found'
            ], 404);
        }

        try {
            // Delete logo from Cloudinary if exists
            if ($club->logo) {
                $publicId = pathinfo(parse_url($club->logo, PHP_URL_PATH), PATHINFO_FILENAME);

                $cloudinary = new Cloudinary(env('CLOUDINARY_URL'));
                $cloudinary->uploadApi()->destroy($publicId);
            }

            $club->delete();

            return response()->json([
                'status' => true,
                'message' => 'Club deleted successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Club deletion failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function students(string $id)
    {
        $club = Club::with([
            'students' => function ($query) {
                $query->select(
                    'students.id',
                    'first_name',
                    'middle_name',
                    'last_name',
                    'roll_number',
                    'class_id',
                    'image'
                );
            },
            'students.class:id,name'
        ])->find($id);

        if (!$club) {
            return response()->json([
                'status' => false,
                'message' => 'Club not found'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'club' => [
                'id' => $club->id,
                'name' => $club->name
            ],
            'students' => $club->students->map(function ($student) {
                return [
                    'id' => $student->id,
                    'name' => trim(
                        $student->first_name . ' ' .
                        $student->middle_name . ' ' .
                        $student->last_name
                    ),
                    'roll_number' => $student->roll_number,
                    'class' => $student->class?->name,
                    'position' => $student->pivot->position,
                    'image' => $student->image
                ];
            })
        ], 200);
    }

}
