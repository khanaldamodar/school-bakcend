<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\WebsiteSetting;
use App\Services\TenantLogger;
use Illuminate\Http\Request;

class WebsiteSettingController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index($domain)
    {
        $websiteSettings = WebsiteSetting::get();

        if ($websiteSettings->isEmpty()) {
            return response()->json(['message' => 'No website settings found.'], 404);
        }
        return response()->json($websiteSettings->first(), 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, $domain)
    {
        $validatedData = $request->validate([
            'hero_title' => 'required|string|max:255',
            'hero_desc' => 'required|string',
            'heroButtonText' => 'nullable|string|max:100',
            'heroButtonUrl' => 'nullable|url|max:255',
            'number_of_teachers' => 'nullable|integer|min:0',
            'number_of_students' => 'nullable|integer|min:0',
            'year_of_experience' => 'nullable|integer|min:0',
            'number_of_events' => 'nullable|integer|min:0',
            'total_awards' => 'nullable|integer|min:0',
            'total_courses' => 'nullable|integer|min:0',
            'mission' => 'nullable|string',
            'vision' => 'nullable|string',
            'pass_rate' => 'nullable|numeric|min:0|max:100',
            'top_score' => 'nullable|numeric|min:0|max:100',
            'history' => 'nullable|string',
            'principal_name' => 'nullable|string|max:255',
            'principal_message' => 'nullable|string',
            'hero_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'principal_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'map_url' => 'nullable|string|max:255',
        ]);

        //  Upload hero image if provided
        if ($request->hasFile('hero_image')) {
            $uploadResult = \App\Helpers\ImageUploadHelper::uploadToCloud(
                $request->file('hero_image'),
                'website/hero'
            );
            $validatedData['hero_image'] = $uploadResult['url'];
            $validatedData['hero_image_public_id'] = $uploadResult['public_id'];
        }

        //  Upload principal image if provided
        if ($request->hasFile('principal_image')) {
            $uploadResult = \App\Helpers\ImageUploadHelper::uploadToCloud(
                $request->file('principal_image'),
                'website/principal'
            );
            $validatedData['principal_image'] = $uploadResult['url'];
            $validatedData['principal_image_public_id'] = $uploadResult['public_id'];
        }

        //  Create the website setting
        $websiteSetting = WebsiteSetting::create($validatedData);

        TenantLogger::logCreate('website_settings', "Website settings created", [
            'id' => $websiteSetting->id,
            'hero_title' => $websiteSetting->hero_title
        ]);

        return response()->json($websiteSetting, 201);
    }


    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $websiteSetting = WebsiteSetting::find($id);

        if (!$websiteSetting) {
            return response()->json(['message' => 'Website setting not found.'], 404);
        }

        return response()->json($websiteSetting, 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $domain, string $id)
    {
        $websiteSetting = WebsiteSetting::find($id);

        if (!$websiteSetting) {
            return response()->json(['message' => 'Website setting not found.'], 404);
        }

        $validatedData = $request->validate([
            'hero_title' => 'sometimes|required|string|max:255',
            'hero_desc' => 'sometimes|required|string',
            'heroButtonText' => 'sometimes|nullable|string|max:100',
            'heroButtonUrl' => 'sometimes|nullable|url|max:255',
            'number_of_teachers' => 'sometimes|nullable|integer|min:0',
            'number_of_students' => 'sometimes|nullable|integer|min:0',
            'year_of_experience' => 'sometimes|nullable|integer|min:0',
            'number_of_events' => 'sometimes|nullable|integer|min:0',
            'total_awards' => 'sometimes|nullable|integer|min:0',
            'total_courses' => 'sometimes|nullable|integer|min:0',
            'mission' => 'sometimes|nullable|string',
            'vision' => 'sometimes|nullable|string',
            'pass_rate' => 'sometimes|nullable|numeric|min:0|max:100',
            'top_score' => 'sometimes|nullable|numeric|min:0|max:100',
            'history' => 'sometimes|nullable|string',
            'principal_name' => 'sometimes|nullable|string|max:255',
            'principal_message' => 'sometimes|nullable|string',
            'hero_image' => 'sometimes|nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'principal_image' => 'sometimes|nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'map_url' => 'sometimes|nullable|string|max:255',
        ]);

        //  Upload hero image if provided
        if ($request->hasFile('hero_image')) {
            $oldHeroPublicId = $websiteSetting->hero_image; // store old image public_id if you save it
            $uploadResult = \App\Helpers\ImageUploadHelper::uploadToCloud(
                $request->file('hero_image'),
                'website/hero',
                $oldHeroPublicId
            );
            $validatedData['hero_image'] = $uploadResult['url'];
        }

        //  Upload principal image if provided
        if ($request->hasFile('principal_image')) {
            $oldPrincipalPublicId = $websiteSetting->principal_image;
            $uploadResult = \App\Helpers\ImageUploadHelper::uploadToCloud(
                $request->file('principal_image'),
                'website/principal',
                $oldPrincipalPublicId
            );
            $validatedData['principal_image'] = $uploadResult['url'];
        }

        $websiteSetting->update($validatedData);

        TenantLogger::logUpdate('website_settings', "Website settings updated", [
            'id' => $websiteSetting->id
        ]);

        return response()->json($websiteSetting, 200);
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $websiteSetting = WebsiteSetting::find($id);

        if (!$websiteSetting) {
            return response()->json(['message' => 'Website setting not found.'], 404);
        }

        $websiteSetting->delete();

        TenantLogger::logDelete('website_settings', "Website settings deleted", [
            'id' => $id
        ]);

        return response()->json(['message' => 'Website setting deleted successfully.'], 200);
    }
}
