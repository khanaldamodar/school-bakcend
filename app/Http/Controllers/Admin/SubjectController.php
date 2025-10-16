<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\SchoolClass;
use App\Models\Admin\Subject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SubjectController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $subjects = Subject::with('teacher:id,name,email')->get();

        if ($subjects->isEmpty()) {
            return response()->json([
                'status' => false,
                'maeesage' => 'No Subjects found'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'maeesage' => 'Subjects Fetched Successfully',
            'subjects' => $subjects
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:subjects,name',
            'theory_marks' => 'required|integer|min:0',
            'practical_marks' => 'required|integer|min:0',
            'teacher_id' => 'nullable|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'maeesage' => 'Validation Failed',
                'error' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();

        $subject = Subject::create($data);
        return response()->json([
            'message' => 'Subject created successfully',
            'data' => $subject
        ], 201);

    }

    /**
     * Display the specified resource.
     */
    public function show(string $domain,int $id)
    {

        // dd($id);
        $subject = Subject::findOrFail($id);
        return response()->json($subject);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $domain,  int $id)
    {
        $subject = Subject::findOrFail($id);

        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'theory_marks' => 'sometimes|integer|min:0',
            'practical_marks' => 'sometimes|integer|min:0',
            'teacher_id' => 'nullable|exists:users,id',
        ]);

        $subject->update($data);

        return response()->json([
            'message' => 'Subject updated successfully',
            'data' => $subject
        ], 201);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $domain,int $id)
    {
        $subject = Subject::findOrFail($id);
        $subject->delete();

        return response()->json([
            'message' => 'Subject deleted successfully'
        ]);
    }



    public function getSubjectsByClass($domain, $classId)
    {
        $schoolClass = SchoolClass::with('subjects')->find($classId);

        if (!$schoolClass) {
            return response()->json([
                'status' => false,
                'message' => 'Class not found',
                'data' => []
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Subjects fetched successfully',
            'data' => $schoolClass->subjects
        ]);
    }


    public function storeClassSubjectTeacher(Request $request)
{
    $request->validate([
        'subject_id' => 'required|exists:subjects,id',
        'assignments' => 'required|array',
        'assignments.*.class_id' => 'required|exists:classes,id',
        'assignments.*.teacher_id' => 'required|exists:teachers,id',
    ]);

    foreach ($request->assignments as $assign) {
        \DB::table('class_subject_teacher')->updateOrInsert(
            [
                'subject_id' => $request->subject_id,
                'class_id' => $assign['class_id'],
            ],
            ['teacher_id' => $assign['teacher_id'], 'updated_at' => now(), 'created_at' => now()]
        );
    }

    return response()->json([
        'status' => true,
        'message' => 'Subject assigned to classes successfully'
    ]);
}

}
