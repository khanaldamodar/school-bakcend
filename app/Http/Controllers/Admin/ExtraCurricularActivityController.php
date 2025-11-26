<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\ExtraCurricularActivity;
use Illuminate\Http\Request;

class ExtraCurricularActivityController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'subject_id' => 'required|exists:subjects,id',
            'activity_name' => 'required|string|max:255',
            'full_marks' => 'nullable|integer|min:0',
            'pass_marks' => 'nullable|integer|min:0',
        ]);

        $activity = ExtraCurricularActivity::create($request->all());

        return response()->json([
            'status' => true,
            'message' => 'Activity added successfully',
            'data' => $activity
        ], 201);
    }

    public function getActivities($subjectId)
    {
        $activities = ExtraCurricularActivity::where('subject_id', $subjectId)->get();

        return response()->json([
            'status' => true,
            'data' => $activities
        ]);
    }
}
