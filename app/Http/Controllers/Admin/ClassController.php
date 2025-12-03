<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\SchoolClass;
use App\Models\Admin\Subject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ClassController extends Controller
{

    public function index()
    {
        $classes = SchoolClass::select('id', 'name', 'section', 'class_teacher_id')
            ->with([
                'classTeacher:id,name',
                'subjects' => function ($query) {
                    $query->select('subjects.id', 'subjects.name', 'subjects.theory_marks', 'subjects.practical_marks', 'subjects.theory_pass_marks');
                },
                'subjects.activities:id,subject_id,class_id,activity_name,full_marks,pass_marks',
            ])
            ->get(); // remove orderBy('name')

        // Filter activities by class_id
        $classes->each(function ($class) {
            $class->subjects->each(function ($subject) use ($class) {
                $filteredActivities = $subject->activities->filter(function ($activity) use ($class) {
                    return $activity->class_id == $class->id;
                })->values();

                $subject->setRelation('activities', $filteredActivities);
            });
        });

        // Sort classes by grade number + section
        $classes = $classes->sortBy(function ($class) {
            // Extract numeric grade
            preg_match('/\d+/', $class->name, $matches);
            $gradeNumber = $matches[0] ?? 0;

            // Combine grade number and section for proper sorting
            return sprintf('%03d-%s', $gradeNumber, $class->section ?? '');
        })->values(); // reindex the collection

        return response()->json([
            'status' => true,
            'message' => 'Classes fetched successfully',
            'data' => $classes
        ]);
    }



    public function store(Request $request, $domain)
    {
        $validator = Validator::make($request->all(), [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('classes')->where(function ($query) use ($request) {
                    return $query->where('section', $request->section);
                }),
            ],
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
                $subjects = Subject::whereIn('id', $data['subject_ids'])->get();
                $syncData = [];
                foreach ($subjects as $subject) {
                    $syncData[$subject->id] = [
                        'teacher_id' => $subject->teacher_id ?? $schoolClass->class_teacher_id
                    ];
                }
                $schoolClass->subjects()->sync($syncData);
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
        $schoolClass = SchoolClass::select('id', 'name', 'section', 'class_teacher_id')
            ->with([
                'classTeacher:id,name',
                'subjects:id,name,theory_marks,practical_marks,theory_pass_marks',
                'subjects.activities' => function ($query) use ($id) {
                    $query->select('id', 'subject_id', 'class_id', 'activity_name', 'full_marks', 'pass_marks')
                        ->where('class_id', $id);
                }
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
            'name' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('classes')->where(function ($query) use ($request) {
                    return $query->where('section', $request->section);
                })->ignore($id),
            ],
            'section' => 'sometimes|string|max:50',
            'class_teacher_id' => 'nullable|exists:teachers,id',
            'subject_ids' => 'nullable|array',
            'subject_ids.*' => 'exists:subjects,id'
        ]);


        \DB::transaction(function () use ($schoolClass, $data) {
            $schoolClass->update([
                'name' => $data['name'] ?? $schoolClass->name,
                'section' => $data['section'] ?? $schoolClass->section,
                'class_teacher_id' => $data['class_teacher_id'] ?? $schoolClass->class_teacher_id,
            ]);

            if (isset($data['subject_ids'])) {
                $subjects = Subject::whereIn('id', $data['subject_ids'])->get();
                $syncData = [];
                foreach ($subjects as $subject) {
                    $syncData[$subject->id] = [
                        'teacher_id' => $subject->teacher_id ?? $schoolClass->class_teacher_id
                    ];
                }
                $schoolClass->subjects()->sync($syncData);
            }
        });

        return response()->json([
            'status' => true,
            'message' => 'Class updated successfully',
            'data' => $schoolClass->fresh('subjects')
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
