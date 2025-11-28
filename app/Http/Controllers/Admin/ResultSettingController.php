<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\ResultSetting;
use Illuminate\Http\Request;

class ResultSettingController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $setting = ResultSetting::first();

        if (!$setting) {
            return response()->json([
                'status' => false,
                'message' => 'Setting not found'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Result setting fetched successfully',
            'data' => $setting
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'setting_id' => 'required|exists:settings,id',
            'total_terms' => 'required|integer|min:1|max:12',
            'calculation_method' => 'required|in:simple,weighted',
            'result_type' => 'required|in:gpa,percentage'
        ]);

        // Prevent duplicate record for same setting
        if (ResultSetting::where('setting_id', $validated['setting_id'])->exists()) {
            return response()->json([
                'status' => false,
                'message' => 'Result setting already exists for this school'
            ], 409);
        }

        $setting = ResultSetting::create($validated);

        return response()->json([
            'status' => true,
            'message' => 'Result setting created successfully',
            'data' => $setting
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $domain, string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $domain, $id)
    {
        $resultSetting = ResultSetting::findOrFail($id);

        $validated = $request->validate([
            'total_terms' => 'sometimes|integer|min:1|max:12',
            'calculation_method' => 'sometimes|in:simple,weighted',
            'result_type' => 'sometimes|in:gpa,percentage'
        ]);

        $resultSetting->update($validated);

        return response()->json([
            'status' => true,
            'message' => 'Result setting updated successfully',
            'data' => $resultSetting
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    
}
