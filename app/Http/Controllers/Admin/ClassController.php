<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\SchoolClass;
use App\Models\Admin\Subject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ClassController extends Controller
{
    public function index()
    {
        $classes = SchoolClass::select('id', 'name')
            ->with([
                'subjects:id,name,theory_marks,practical_marks',
                'subjects.activities:id,subject_id,activity_name,full_marks,pass_marks'
            ])
            ->get();


        return response()->json([
            'status' => true,
            'message' => 'Classes fetched successfully',
            'data' => $classes
        ]);
    }

    public function store(Request $request, $domain)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:classes,name',
            'section' => 'nullable|string|max:50',
            'subject_ids' => 'nullable|array',
            'subject_ids.*' => 'exists:subjects,id',
            'class_teacher_id' => 'nullable|exists:teachers,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();

        try {
            $schoolClass = SchoolClass::create([
                'name' => $data['name'],
                'section' => $data['section'] ?? null,
                'class_teacher_id' => $data['class_teacher_id'] ?? null,
            ]);

            if (!empty($data['subject_ids'])) {
                $schoolClass->subjects()->sync($data['subject_ids']);
            }

            return response()->json([
                'status' => true,
                'message' => 'Class created successfully',
                'data' => $schoolClass->load(['subjects', 'classTeacher']),
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => 'Something went wrong',
                'error' => $e->getMessage(),
            ], 500);
        }
    }




    public function show($domain, $id)
    {
        $schoolClass = SchoolClass::select('id', 'name')
            ->with([
                'subjects:id,name,theory_marks,practical_marks',
                'subjects.activities:id,subject_id,activity_name,full_marks,pass_marks'
            ])->findOrFail($id);

        return response()->json([
            'status' => true,
            'message' => 'Class fetched successfully',
            'data' => $schoolClass
        ]);
    }

    public function update(Request $request, $domain, $id)
    {
        $schoolClass = SchoolClass::findOrFail($id);

        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'subject_ids' => 'nullable|array',
            'subject_ids.*' => 'exists:subjects,id'
        ]);

        $schoolClass->update(['name' => $data['name'] ?? $schoolClass->name]);

        if (isset($data['subject_ids'])) {
            $schoolClass->subjects()->sync($data['subject_ids']);
        }

        return response()->json([
            'status' => true,
            'message' => 'Class updated successfully',
            'data' => $schoolClass->load('subjects')
        ]);
    }

    public function destroy($domain, $id)
    {
        $schoolClass = SchoolClass::findOrFail($id);
        $schoolClass->delete();

        return response()->json([
            'status' => true,
            'message' => 'Class deleted successfully'
        ]);
    }
}
