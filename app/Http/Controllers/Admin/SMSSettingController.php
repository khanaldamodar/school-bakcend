<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\AcademicYear;
use App\Models\Admin\SMSSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SMSSettingController extends Controller
{
    /**
     * List SMS settings (optionally filtered by academic year).
     */
    public function index(Request $request, $domain)
    {
        $query = SMSSetting::with('academicYear');

        if ($request->filled('academic_year_id')) {
            $query->where('academic_year_id', $request->academic_year_id);
        }

        return response()->json([
            'status' => true,
            'data' => $query->get(),
        ]);
    }

    /**
     * Create a new SMS setting for events.
     */
    public function store(Request $request, $domain)
    {
        $validator = Validator::make($request->all(), [
            'academic_year_id' => 'required|exists:academic_years,id',
            'event_type' => 'nullable|string|max:100',
            'days_before' => 'required|integer|min:0',
            'target_group' => 'required|in:parents,students,teachers,all',
            'is_active' => 'boolean',
            'message_template' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        // Ensure academic year exists and is valid
        $academicYear = AcademicYear::find($data['academic_year_id']);
        if (!$academicYear) {
            return response()->json([
                'status' => false,
                'message' => 'Academic year not found',
            ], 404);
        }

        $setting = SMSSetting::create($data);

        return response()->json([
            'status' => true,
            'message' => 'SMS setting created successfully',
            'data' => $setting->load('academicYear'),
        ], 201);
    }

    /**
     * Update an existing SMS setting.
     */
    public function update(Request $request, $domain, $id)
    {
        $setting = SMSSetting::find($id);

        if (!$setting) {
            return response()->json([
                'status' => false,
                'message' => 'SMS setting not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'academic_year_id' => 'sometimes|exists:academic_years,id',
            'event_type' => 'nullable|string|max:100',
            'days_before' => 'sometimes|integer|min:0',
            'target_group' => 'sometimes|in:parents,students,teachers,all',
            'is_active' => 'sometimes|boolean',
            'message_template' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        if (isset($data['academic_year_id'])) {
            $academicYear = AcademicYear::find($data['academic_year_id']);
            if (!$academicYear) {
                return response()->json([
                    'status' => false,
                    'message' => 'Academic year not found',
                ], 404);
            }
        }

        $setting->update($data);

        return response()->json([
            'status' => true,
            'message' => 'SMS setting updated successfully',
            'data' => $setting->load('academicYear'),
        ], 200);
    }

    /**
     * Delete an SMS setting.
     */
    public function destroy($domain, $id)
    {
        $setting = SMSSetting::find($id);

        if (!$setting) {
            return response()->json([
                'status' => false,
                'message' => 'SMS setting not found',
            ], 404);
        }

        $setting->delete();

        return response()->json([
            'status' => true,
            'message' => 'SMS setting deleted successfully',
        ], 200);
    }
}


