<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\SchoolClass;
use App\Models\Admin\Subject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;


class SubjectController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $subjects = Subject::with('teacher:id,name,email', 'activities')->get();

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
    // public function store(Request $request)
    // {

    //     $validator = Validator::make($request->all(), [
    //         'name' => 'required|string|max:255|unique:subjects,name',
    //         "subject_code" => "nullable|string",
    //         'theory_marks' => 'required|integer|min:0',
    //         'practical_marks' => 'required|integer|min:0',
    //         'teacher_id' => 'nullable|exists:users,id',
    //     ]);

    //     if ($validator->fails()) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'Validation Failed',
    //             'error' => $validator->errors()
    //         ], 422);
    //     }

    //     $data = $validator->validated();

    //     $subject = Subject::create($data);
    //     return response()->json([
    //         'message' => 'Subject created successfully',
    //         'data' => $subject
    //     ], 201);

    // }

    /**
     * Display the specified resource.
     */
    public function show(string $domain, int $id)
    {

        // dd($id);
        $subject = Subject::with('activities')->findOrFail($id);
        return response()->json($subject);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $domain, int $id)
    {
        $subject = Subject::with('activities')->findOrFail($id);

        $request->validate([
            'name' => 'sometimes|string|max:255|unique:subjects,name,' . $id,
            'theory_marks' => 'sometimes|integer|min:0',
            'practical_marks' => 'sometimes|integer|min:0',
            'theory_pass_marks' => 'sometimes|decimal|min:0',
            'subject_code' => 'nullable|string',
            'teacher_id' => 'nullable|exists:users,id',

            // ACTIVITY VALIDATION
            'activities' => 'nullable|array',
            'activities.*.id' => 'nullable|exists:extra_curricular_activities,id',
            'activities.*.activity_name' => 'required|string|max:255',
            'activities.*.full_marks' => 'nullable|integer|min:0',
            'activities.*.pass_marks' => 'nullable|integer|min:0',
        ]);

        DB::transaction(function () use ($request, $subject) {

            // UPDATE SUBJECT
            $subject->update($request->only([
                'name',
                'theory_marks',
                'practical_marks',
                'teacher_id'
            ]));

            if ($request->has('activities')) {

                $existingIds = $subject->activities->pluck('id')->toArray();
                $incomingIds = collect($request->activities)
                    ->pluck('id')
                    ->filter()
                    ->toArray();

                // DELETE REMOVED ACTIVITIES
                $deleteIds = array_diff($existingIds, $incomingIds);
                if (!empty($deleteIds)) {
                    \App\Models\Admin\ExtraCurricularActivity::whereIn('id', $deleteIds)->delete();
                }

                // UPDATE OR CREATE ACTIVITIES
                foreach ($request->activities as $activity) {

                    if (isset($activity['id'])) {
                        // UPDATE
                        $subject->activities()
                            ->where('id', $activity['id'])
                            ->update([
                                'activity_name' => $activity['activity_name'],
                                'full_marks' => $activity['full_marks'] ?? null,
                                'pass_marks' => $activity['pass_marks'] ?? null,
                                'theory_pass_marks' => $activity['theory_pass_marks'] ?? null,
                            ]);
                    } else {
                        // CREATE NEW
                        $subject->activities()->create([
                            'activity_name' => $activity['activity_name'],
                            'full_marks' => $activity['full_marks'] ?? null,
                            'pass_marks' => $activity['pass_marks'] ?? null,
                        ]);
                    }
                }
            }
        });

        return response()->json([
            'status' => true,
            'message' => 'Subject and activities updated successfully',
            'data' => $subject->fresh('activities')
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $domain, int $id)
    {
        $subject = Subject::findOrFail($id);
        $subject->delete();

        return response()->json([
            'message' => 'Subject deleted successfully'
        ]);
    }

    public function getSubjectsByClass($domain, $classId)
    {
        $schoolClass = SchoolClass::with([
            'subjects.activities' => function ($query) use ($classId) {
                $query->where('class_id', $classId);
            }
        ])->find($classId);

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

    public function updateClassSubjectTeacher(Request $request, string $domain, int $id)
    {
        $request->validate([
            'teacher_id' => 'required|exists:teachers,id',
        ]);

        // Check if the assignment exists
        $assignment = \DB::table('class_subject_teacher')->where('id', $id)->first();

        if (!$assignment) {
            return response()->json([
                'status' => false,
                'message' => 'Assignment not found'
            ], 404);
        }

        // Update the teacher assignment
        \DB::table('class_subject_teacher')
            ->where('id', $id)
            ->update([
                'teacher_id' => $request->teacher_id,
                'updated_at' => now()
            ]);

        // Fetch the updated record with related data
        $updatedAssignment = \DB::table('class_subject_teacher as cst')
            ->join('subjects as s', 'cst.subject_id', '=', 's.id')
            ->join('classes as c', 'cst.class_id', '=', 'c.id')
            ->join('teachers as t', 'cst.teacher_id', '=', 't.id')
            ->where('cst.id', $id)
            ->select(
                'cst.id',
                'cst.subject_id',
                's.name as subject_name',
                'cst.class_id',
                'c.name as class_name',
                'cst.teacher_id',
                't.name as teacher_name',
                'cst.updated_at',
                'cst.created_at'
            )
            ->first();

        return response()->json([
            'status' => true,
            'message' => 'Teacher assignment updated successfully',
            'data' => $updatedAssignment
        ]);
    }

    public function getClassSubjectTeacher(Request $request)
    {
        $query = \DB::table('class_subject_teacher as cst')
            ->join('subjects as s', 'cst.subject_id', '=', 's.id')
            ->join('classes as c', 'cst.class_id', '=', 'c.id')
            ->join('teachers as t', 'cst.teacher_id', '=', 't.id')
            ->select(
                'cst.id',
                'cst.subject_id',
                's.name as subject_name',
                'cst.class_id',
                'c.name as class_name',
                'cst.teacher_id',
                't.name as teacher_name',
                'cst.updated_at',
                'cst.created_at'
            );

        // Optional Filtering
        if ($request->has('subject_id')) {
            $query->where('cst.subject_id', $request->subject_id);
        }

        if ($request->has('class_id')) {
            $query->where('cst.class_id', $request->class_id);
        }

        if ($request->has('teacher_id')) {
            $query->where('cst.teacher_id', $request->teacher_id);
        }

        $data = $query->get();

        return response()->json([
            'status' => true,
            'data' => $data
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:subjects,name',
            'subject_code' => 'nullable|string',
            'theory_marks' => 'required|integer|min:0',
            'theory_pass_marks' => 'required|numeric|min:0',
            'practical_marks' => 'required|integer|min:0',
            'teacher_id' => 'nullable|exists:users,id',

            // NESTED VALIDATION
            'activities' => 'nullable|array',
            'activities.*.activity_name' => 'required|string|max:255',
            'activities.*.full_marks' => 'nullable|integer|min:0',
            'activities.*.pass_marks' => 'nullable|integer|min:0',
        ]);

        \DB::beginTransaction();

        try {

            //  Create Subject
            $subject = Subject::create($request->only([
                'name',
                'subject_code',
                'theory_marks',
                'practical_marks',
                'teacher_id',
                'theory_pass_marks'
            ]));

            //  Create Activities (if provided)
            if ($request->has('activities')) {
                $subject->activities()->createMany($request->activities);
            }

            \DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Subject & activities created successfully',
                'data' => $subject->load('activities')
            ], 201);

        } catch (\Exception $e) {

            \DB::rollBack();

            return response()->json([
                'status' => false,
                'message' => 'Failed to create subject & activities',
                'error' => $e->getMessage()
            ], 500);
        }
    }


}
