<?php

namespace App\Http\Controllers\Admin;
use App\Helpers\ImageUploadHelper;

use App\Http\Controllers\Controller;
use App\Models\Admin\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SettingController extends Controller
{

    // ?To get the settings of the school
    public function index()
    {

        $settings = Setting::with('resultSetting', 'resultSetting.terms')->first();
        // dd($settings);

        if (!$settings) {
            return response()->json(['message' => 'No settings found'], 404);
        }

        return response()->json($settings, 200);
    }


    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => "required|string",
            "about" => 'string|required',
            "logo" => 'image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            "favicon" => 'image|mimes:jpeg,png,jpeg,gif,svg|max:2048',
            "address" => 'string|required',
            "phone" => 'string|required',
            "email" => 'string|email|required',
            "facebook" => 'string|url|nullable',
            "twitter" => 'string|url|nullable',
            "start_time" => 'date_format:H:i:s|required',
            "end_time" => 'date_format:H:i:s|required',
            "isWeighted" => "nullable",
            "number_of_exams" => "nullable",
            'district' => 'string|nullable',
            'local_body' => 'string|nullable',
            'ward' => 'integer|nullable'
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }
        $data = $validator->validated();

        if ($request->hasFile('logo')) {
            $logoPath = $request->file('logo')->store('logos', 'public');
            $data['logo'] = $logoPath;
        }
        if ($request->hasFile('favicon')) {
            $faviconPath = $request->file('favicon')->store('favicons', 'public');
            $data['favicon'] = $faviconPath;
        }
        $settings = Setting::create($data);

        return response()->json([
            'status' => true,
            'message' => "Setting Created Successfully",
            'data' => $settings
        ], 200);

    }









    // ?To update the settings of the school
    public function update(Request $request)
    {
        $settings = Setting::first();

        if (!$settings) {
            return response()->json(['message' => 'Settings not found'], 404);
        }

        $validatedData = $request->validate([
            'name' => 'sometimes|string|max:255',
            'about' => 'sometimes|string',
            'logo' => 'sometimes|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'favicon' => 'sometimes|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'address' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|max:20',
            'email' => 'sometimes|email|max:255',
            'facebook' => 'sometimes|url|max:255',
            'twitter' => 'sometimes|url|max:255',
            'start_time' => 'sometimes|date_format:H:i:s',
            'end_time' => 'sometimes|date_format:H:i:s',
            'school_type' => 'sometimes|string|max:100',
            'established_date' => 'sometimes|date',
            'principle' => 'sometimes|string|max:100',
            'favicon_public_id' => "sometimes|string",
            'logo_public_id' => 'sometimes|string',
            'district' => 'sometimes|string',
            'local_body' => 'sometimes|string',
            'ward' => 'sometimes|integer'
        ]);

        // === Upload Logo to Cloudinary ===
        if ($request->hasFile('logo')) {
            $upload = ImageUploadHelper::uploadToCloud(
                $request->file('logo'),
                'school/settings/logos',
                $settings->logo_public_id ?? null // pass old public_id if exists
            );

            if ($upload) {
                $validatedData['logo'] = $upload['url'];
                $validatedData['logo_public_id'] = $upload['public_id'];
            }
        }

        // === Upload Favicon to Cloudinary ===
        if ($request->hasFile('favicon')) {
            $upload = ImageUploadHelper::uploadToCloud(
                $request->file('favicon'),
                'school/settings/favicons',
                $settings->favicon_public_id ?? null
            );

            if ($upload) {
                $validatedData['favicon'] = $upload['url'];
                $validatedData['favicon_public_id'] = $upload['public_id'];
            }
        }

        // === Update other fields ===
        $settings->update($validatedData);

        return response()->json([
            'status' => true,
            'message' => 'Settings updated successfully',
            'data' => $settings
        ], 200);
    }

}
