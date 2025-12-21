<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\ExtraCurricularActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\TenantLogger;

class ExtraCurricularActivityController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'subject_id' => 'required|exists:subjects,id',
            'class_id' => 'nullable|exists:classes,id',
            'activity_name' => 'required|string|max:255',
            'full_marks' => 'nullable|integer|min:0',
            'pass_marks' => 'nullable|integer|min:0',
        ]);

        TenantLogger::activityInfo('Creating extra curricular activity', ['data' => $request->all()]);
        $activity = ExtraCurricularActivity::create($request->all());

        return response()->json([
            'status' => true,
            'message' => 'Activity added successfully',
            'data' => $activity
        ], 201);
    }

    public function bulkStore(Request $request)
    {
        $request->validate([
            'activities' => 'required|array|min:1',
            'activities.*.subject_id' => 'required|exists:subjects,id',
            'activities.*.class_id' => 'nullable|exists:classes,id',
            'activities.*.activity_name' => 'required|string|max:255',
            'activities.*.full_marks' => 'nullable|integer|min:0',
            'activities.*.pass_marks' => 'nullable|integer|min:0',
        ]);

        $activitiesData = $request->input('activities');
        $createdActivities = [];
        
        TenantLogger::activityInfo('Starting bulk extra curricular activity creation', ['count' => count($activitiesData)]);

        ExtraCurricularActivity::insert($activitiesData);

        return response()->json([
            'status' => true,
            'message' => 'Activities added successfully',
        ], 201);
    }

    public function getActivities(Request $request, $subjectId)
    {
        $query = ExtraCurricularActivity::where('subject_id', $subjectId);

        if ($request->has('class_id')) {
            $query->where('class_id', $request->class_id);
        }

        $activities = $query->get();

        return response()->json([
            'status' => true,
            'data' => $activities
        ]);
    }


    public function update(Request $request, $domain, $id)
    {
        TenantLogger::activityInfo('Updating extra curricular activity', ['activity_id' => $id, 'data' => $request->all()]);
        // Validate incoming request
        $validatedData = $request->validate([
            'subject_id' => 'required|exists:subjects,id',
            'class_id' => 'nullable|exists:classes,id',
            'activity_name' => 'required|string|max:255',
            'full_marks' => 'nullable|integer|min:0',
            'pass_marks' => 'nullable|integer|min:0',
        ]);

        try {
            // Find activity
            $activity = ExtraCurricularActivity::find($id);

            if (!$activity) {
                return response()->json([
                    'status' => false,
                    'message' => 'Activity not found',
                ], 404);
            }

            // Update activity
            $activity->update($validatedData);

            return response()->json([
                'status' => true,
                'message' => 'Activity updated successfully',
                'data' => $activity
            ], 200);

        } catch (\Exception $e) {
            TenantLogger::activityError('Update activity failed', ['error' => $e->getMessage(), 'id' => $id]);
            return response()->json([
                'status' => false,
                'message' => 'Something went wrong while updating the activity.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function delete(Request $request, $domain, $id)
    {
        try {
            // Find the activity
            TenantLogger::activityInfo('Deleting extra curricular activity', ['activity_id' => $id]);
            $activity = ExtraCurricularActivity::find($id);

            if (!$activity) {
                return response()->json([
                    'status' => false,
                    'message' => 'Activity not found',
                ], 404);
            }

            // Delete the record
            $activity->delete();

            return response()->json([
                'status' => true,
                'message' => 'Activity deleted successfully',
            ], 200);

        } catch (\Exception $e) {
            TenantLogger::activityError('Delete activity failed', ['error' => $e->getMessage(), 'id' => $id]);
            return response()->json([
                'status' => false,
                'message' => 'Something went wrong while deleting the activity.',
                'error' => $e->getMessage()
            ], 500);
        }
    }


}
