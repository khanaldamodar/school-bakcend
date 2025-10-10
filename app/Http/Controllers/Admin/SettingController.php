<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\Setting;
use Illuminate\Http\Request;

class SettingController extends Controller
{

    // ?To get the settings of the school
    public function index()
    {

        $settings = Setting::first();

        if ($settings->isEmpty()) {
            return response()->json(['message' => 'No settings found'], 404);
        }

        return response()->json($settings, 200);
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
        ]);
        if ($request->hasFile('logo')) {
            $logoPath = $request->file('logo')->store('logos', 'public');
            $validatedData['logo'] = $logoPath;
        }
        if ($request->hasFile('favicon')) {
            $faviconPath = $request->file('favicon')->store('favicons', 'public');
            $validatedData['favicon'] = $faviconPath;
        }
        $settings->update($validatedData);
        return response()->json($settings, 200);
    }

}
