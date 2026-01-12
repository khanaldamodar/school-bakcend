<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\Event;
use App\Models\Admin\ResultSetting;
use Illuminate\Http\Request;
use App\Services\TenantLogger;

class ResultSettingController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, $domain)
    {
        $academicYearId = $request->query('academic_year_id');
        
        $query = ResultSetting::with('terms');
        
        if ($academicYearId) {
            $query->where('academic_year_id', $academicYearId);
        } else {
            // Default to current academic year
            $query->whereHas('academicYear', function($q) {
                $q->where('is_current', true);
            });
        }
        
        $setting = $query->first();

        // If no setting for current year, just get any existing one as fallback
        if (!$setting && !$academicYearId) {
            $setting = ResultSetting::with('terms')->latest()->first();
        }

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
    public function store(Request $request, $domain)
    {
        $validated = $request->validate([
            'setting_id' => 'required|exists:settings,id',
            'academic_year_id' => 'required|exists:academic_years,id',
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

        // Prevent duplicate record for same setting and academic year
        if (ResultSetting::where('setting_id', $validated['setting_id'])
            ->where('academic_year_id', $validated['academic_year_id'])
            ->exists()) {
            return response()->json([
                'status' => false,
                'message' => 'Result setting already exists for this school and academic year'
            ], 409);
        }

        // Create ResultSetting
        $setting = ResultSetting::create($validated);


        //To create Event for the examinaltion, 

        if (!empty($validated['terms'])) {

            foreach ($validated['terms'] as $termData) {
                // Ensure academic_year_id is set for the term
                $termData['academic_year_id'] = $setting->academic_year_id;

                // Create term
                $term = $setting->terms()->create($termData);

                // Create Exam Event only if exam_date exists
                if (!empty($termData['exam_date'])) {
                    Event::create([
                        'title' => $termData['name'] . ' Exam',
                        'date' => $termData['exam_date'],
                        'type' => 'exam',
                    ]);
                }
            }
        }


        TenantLogger::logCreate('result_settings', "Result setting created", [
            'id' => $setting->id,
            'calculation_method' => $setting->calculation_method
        ]);

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
            'academic_year_id' => 'sometimes|exists:academic_years,id',
            'total_terms' => 'sometimes|integer|min:1|max:12',
            'calculation_method' => 'sometimes|in:simple,weighted',
            'result_type' => 'sometimes|in:gpa,percentage',
            // 'setting_id' => 'sometimes|exists:settings,id',
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

        // Handle weighted calculation
        if (isset($validated['calculation_method']) && $validated['calculation_method'] !== 'weighted') {
            $validated['term_weights'] = null;
        }

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

        if (!empty($validated['terms'])) {
            $termIdsFromRequest = [];
            foreach ($validated['terms'] as $termData) {
                if (!empty($termData['id'])) {
                    // Update existing term
                    $term = $resultSetting->terms()->find($termData['id']);
                    if ($term) {
                        $term->update($termData);
                        $termIdsFromRequest[] = $term->id;
                    }
                } else {
                    // Create new term
                    $termData['academic_year_id'] = $resultSetting->academic_year_id;
                    $newTerm = $resultSetting->terms()->create($termData);
                    $termIdsFromRequest[] = $newTerm->id;
                }
            }

            // Delete terms that were removed from request
            $resultSetting->terms()->whereNotIn('id', $termIdsFromRequest)->delete();
        }

        TenantLogger::logUpdate('result_settings', "Result setting updated", [
            'id' => $resultSetting->id
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Result setting updated successfully',
            'data' => $resultSetting->load('terms')
        ]);
    }


    public function destroy(string $domain, string $id)
    {
        $resultSettings = ResultSetting::findOrFail($id);
        $resultSettings->delete();

        TenantLogger::logDelete('result_settings', "Result setting deleted", [
            'id' => $id
        ]);
    }


}
