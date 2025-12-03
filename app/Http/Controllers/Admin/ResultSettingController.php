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
        $setting = ResultSetting::with('terms')->get();

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
            'result_type' => 'required|in:gpa,percentage',
            'term_weights' => 'nullable|array',
            'evaluation_per_term' => 'sometimes|boolean',
            'terms' => 'required|array', // array of terms
            'terms.*.name' => 'required|string',
            'terms.*.weight' => 'nullable|integer',
            'terms.*.exam_date' => 'nullable|date',
            'terms.*.publish_date' => 'nullable|date',
            'terms.*.start_date' => 'nullable|date',
            'terms.*.end_date' => 'nullable|date',
        ]);

        // Prevent duplicate record for same setting
        if (ResultSetting::where('setting_id', $validated['setting_id'])->exists()) {
            return response()->json([
                'status' => false,
                'message' => 'Result setting already exists for this school'
            ], 409);
        }

        // Create ResultSetting
        $setting = ResultSetting::create($validated);

        // Create terms for this ResultSetting
        if (!empty($validated['terms'])) {
            $setting->terms()->createMany($validated['terms']);
        }

        return response()->json([
            'status' => true,
            'message' => 'Result setting created successfully',
            'data' => $setting->load('terms')
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
            'result_type' => 'sometimes|in:gpa,percentage',
            'term_weights' => 'nullable|array',
            'evaluation_per_term' => 'sometimes|boolean',
            'terms' => 'nullable|array',
            'terms.*.id' => 'sometimes|exists:terms,id',
            'terms.*.name' => 'required_with:terms|string',
            'terms.*.weight' => 'nullable|integer',
            'terms.*.exam_date' => 'nullable|date',
            'terms.*.publish_date' => 'nullable|date',
            'terms.*.start_date' => 'nullable|date',
            'terms.*.end_date' => 'nullable|date',
        ]);

        // If calculation_method is not weighted, remove term_weights
        if (
            isset($validated['calculation_method']) &&
            $validated['calculation_method'] !== 'weighted'
        ) {
            $validated['term_weights'] = null;
        }

        // If weighted, confirm weights sum to 100
        if (
            isset($validated['calculation_method']) &&
            $validated['calculation_method'] === 'weighted' &&
            isset($validated['term_weights'])
        ) {
            if (array_sum($validated['term_weights']) != 100) {
                return response()->json([
                    'message' => 'Total weight must be exactly 100%'
                ], 422);
            }
        }

        // Update ResultSetting
        $resultSetting->update($validated);

        // Update or create terms if provided
        if (!empty($validated['terms'])) {
            foreach ($validated['terms'] as $termData) {
                if (isset($termData['id'])) {
                    // Update existing term
                    $term = $resultSetting->terms()->find($termData['id']);
                    if ($term) {
                        $term->update($termData);
                    }
                } else {
                    // Create new term
                    $resultSetting->terms()->create($termData);
                }
            }
        }

        return response()->json([
            'status' => true,
            'message' => 'Result setting updated successfully',
            'data' => $resultSetting->load('terms')
        ]);
    }

}
