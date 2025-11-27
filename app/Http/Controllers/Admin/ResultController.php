<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\ExtraCurricularActivity;
use App\Models\Admin\Result;
use App\Models\Admin\ResultActivity;
use App\Models\Admin\SchoolClass;
use App\Models\Admin\Student;
use App\Models\Admin\Subject;
use App\Models\Admin\Teacher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ResultController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // Base query with eager loading
        $query = Result::with([
            'student:id,first_name,last_name,class_id',
            'class:id,name,class_code',
            'subject:id,name,theory_marks,practical_marks',
            'teacher:id,first_name,last_name'
        ]);

        if ($user->role === 'teacher') {
            $teacher = $user->teacher;

            // Teacher can only see results of:
            // 1. Their own subjects OR
            // 2. Class they are class teacher of
            $query->where(function ($q) use ($teacher) {
                $q->where('teacher_id', $teacher->id)
                    ->orWhere('class_id', $teacher->class_teacher_of_id);
            });
        } elseif ($user->role === 'student') {
            // Student sees only their own results
            $query->where('student_id', $user->student->id ?? 0);
        } elseif ($user->role === 'parent') {
            // Parent sees results of their children
            $childIds = $user->parent->students()->pluck('id');
            $query->whereIn('student_id', $childIds);
        }

        $results = $query->get();

        return response()->json([
            'status' => true,
            'message' => 'Results fetched successfully',
            'data' => $results
        ], 200);
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $user = $request->user();
            // dd($user->id);
            $teacherId = Teacher::where('user_id', $user->id)->value('id');
            // dd($teacherId);

            $validated = $request->validate([
                'student_id' => 'required|exists:students,id',
                'class_id' => 'required|exists:classes,id',
                'subject_id' => 'required|exists:subjects,id',
                'marks_theory' => 'required|integer|min:0',
                'marks_practical' => 'required|integer|min:0',
                'exam_type' => 'nullable|string|max:255',
                'exam_date' => 'nullable',
            ]);

            $subject = \App\Models\Admin\Subject::findOrFail($validated['subject_id']);
            $max_theory = $subject->theory_marks ?? 0;
            $max_practical = $subject->practical_marks ?? 0;

            $marks_obtained = $validated['marks_theory'] + $validated['marks_practical'];
            $max_marks = $max_theory + $max_practical;
            $gpa = $max_marks > 0 ? round(($marks_obtained / $max_marks) * 4, 2) : 0;

            $result = Result::create([
                'student_id' => $validated['student_id'],
                'class_id' => $validated['class_id'],
                'subject_id' => $validated['subject_id'],
                'teacher_id' => $user ? $user->id : null,
                'marks_theory' => $validated['marks_theory'],
                'marks_practical' => $validated['marks_practical'],
                'gpa' => $gpa,
                'exam_type' => $validated['exam_type'] ?? null,
                'exam_date' => $validated['exam_date'] ?? null,
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Result added successfully',
                'data' => $result
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Internal Server Error',
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 500);
        }
    }




    /**
     * Display the specified resource.
     */
    public function show($domain, $id)
    {
        $result = Result::with([
            'student:id,first_name,last_name,class_id',
            'class:id,name,class_code',
            'subject:id,name,theory_marks,practical_marks',
            'teacher:id,first_name,last_name'
        ])->findOrFail($id);

        return response()->json([
            'status' => true,
            'message' => 'Result fetched successfully',
            'data' => $result
        ], 200);
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $domain, $id)
    {
        $user = $request->user();

        $result = Result::findOrFail($id);

        $validated = $request->validate([
            'marks_theory' => 'sometimes|integer|min:0',
            'marks_practical' => 'sometimes|integer|min:0',
            'exam_type' => 'nullable|string|max:255',
            'exam_date' => 'nullable',
        ]);

        // Permission check for teacher
        if ($user->role === 'teacher') {
            $teacher = $user->teacher;

            // Teacher can only update if:
            // 1. Student belongs to their class_teacher_of_id OR
            // 2. Subject belongs to the teacher
            if (
                $result->class_id != $teacher->class_teacher_of_id &&
                !$teacher->subjects->pluck('id')->contains($result->subject_id)
            ) {
                return response()->json([
                    'status' => false,
                    'message' => 'Not allowed to update this result'
                ], 403);
            }
        }

        // Update theory and practical marks if provided
        if (isset($validated['marks_theory'])) {
            $result->marks_theory = $validated['marks_theory'];
        }
        if (isset($validated['marks_practical'])) {
            $result->marks_practical = $validated['marks_practical'];
        }

        // Update exam type if provided
        if (isset($validated['exam_type'])) {
            $result->exam_type = $validated['exam_type'];
        }

        // Recalculate GPA
        $subject = $result->subject;
        $max_theory = $subject->theory_marks ?? 0;
        $max_practical = $subject->practical_marks ?? 0;
        $marks_obtained = $result->marks_theory + $result->marks_practical;
        $max_marks = $max_theory + $max_practical;
        $result->gpa = $max_marks > 0 ? round(($marks_obtained / $max_marks) * 4, 2) : 0;

        $result->save();

        return response()->json([
            'status' => true,
            'message' => 'Result updated successfully',
            'data' => $result
        ], 200);
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, $domain, $id)
    {
        $user = $request->user();
        $result = Result::findOrFail($id);

        // Role-based permission check
        if ($user->role === 'teacher') {
            $teacher = $user->teacher;

            // Teacher can only delete if:
            // 1. They added the result (teacher_id) OR
            // 2. They are class teacher of that class
            if (
                $result->teacher_id != $teacher->id &&
                $result->class_id != $teacher->class_teacher_of_id
            ) {
                return response()->json([
                    'status' => false,
                    'message' => 'Not allowed to delete this result'
                ], 403);
            }
        } elseif ($user->role === 'student' || $user->role === 'parent') {
            // Students and parents cannot delete
            return response()->json([
                'status' => false,
                'message' => 'Not allowed to delete this result'
            ], 403);
        }

        $result->delete();

        return response()->json([
            'status' => true,
            'message' => 'Result deleted successfully'
        ], 200);
    }


    public function resultsByClass(Request $request, $domain, $classId)
    {
        $user = $request->user();

        // Base query with eager loading
        $query = Result::with([
            'student:id,first_name,last_name,class_id',
            'class:id,name,class_code',
            'subject:id,name,theory_marks,practical_marks',
            'teacher:id,first_name,last_name'
        ])->where('class_id', $classId);

        // Role-based filtering
        if ($user->role === 'teacher') {
            $teacher = $user->teacher;

            // Teacher can only see results for:
            // 1. Their subjects OR
            // 2. The class they are class teacher of
            $query->where(function ($q) use ($teacher, $classId) {
                $q->where('teacher_id', $teacher->id)
                    ->orWhere(function ($q2) use ($teacher, $classId) {
                        $q2->where('class_id', $classId)
                            ->where('class_id', $teacher->class_teacher_of_id);
                    });
            });
        } elseif ($user->role === 'student') {
            $query->where('student_id', $user->student->id ?? 0);
        } elseif ($user->role === 'parent') {
            $childIds = $user->parent->students()->pluck('id');
            $query->whereIn('student_id', $childIds);
        }

        $results = $query->get();

        return response()->json([
            'status' => true,
            'message' => 'Results fetched successfully for class ' . $classId,
            'data' => $results
        ], 200);
    }


    public function resultsByClassAndStudent(Request $request, $domain, $classId)
    {
        $name = $request->query('name'); // ?name=John

        $user = $request->user();

        $query = Result::with([
            'student:id,first_name,last_name,class_id',
            'class:id,name,class_code',
            'subject:id,name,theory_marks,practical_marks',
            'teacher:id,first_name,last_name'
        ])->where('class_id', $classId)
            ->whereHas('student', function ($q) use ($name) {
                $q->where('first_name', 'like', '%' . $name . '%')
                    ->orWhere('last_name', 'like', '%' . $name . '%');
            });

        // Role-based filtering
        if ($user->role === 'teacher') {
            $teacher = $user->teacher;
            $query->where(function ($q) use ($teacher, $classId) {
                $q->where('teacher_id', $teacher->id)
                    ->orWhere('class_id', $teacher->class_teacher_of_id);
            });
        } elseif ($user->role === 'student') {
            $query->where('student_id', $user->student->id ?? 0);
        } elseif ($user->role === 'parent') {
            $childIds = $user->parent->students()->pluck('id');
            $query->whereIn('student_id', $childIds);
        }

        $results = $query->get();

        return response()->json([
            'status' => true,
            'message' => 'Results fetched successfully for class ' . $classId . ' and student name ' . $name,
            'data' => $results
        ], 200);
    }


    public function studentResult($domain)
    {
        // get the logged in user
        $user = auth()->user();

        // find student based on user_id
        $student = Student::with('class:id,name')
            ->where('user_id', $user->id)
            ->firstOrFail();

        // fetch all results including past classes
        $results = Result::with('subject:id,name,theory_marks,practical_marks')
            ->where('student_id', $student->id) // use student.id, not user_id
            ->get()
            ->groupBy(['class_id', 'exam_type']); // Group by class + exam

        $formattedResults = [];
        $overallGPA = []; // store overall GPA per class & exam type

        foreach ($results as $classId => $exams) {
            $className = SchoolClass::find($classId)->name ?? 'Unknown Class';

            foreach ($exams as $examType => $subjects) {
                // Format individual subjects
                $formattedResults[$className][$examType] = $subjects->map(function ($result) {
                    return [
                        'subject_name' => $result->subject->name,
                        'marks_theory' => $result->marks_theory,
                        'max_theory' => $result->subject->theory_marks,
                        'marks_practical' => $result->marks_practical,
                        'max_practical' => $result->subject->practical_marks,
                        'gpa' => $result->gpa,
                        'exam_type' => $result->exam_type,
                    ];
                });

                // Calculate average GPA for this exam type
                $overallGPA[$className][$examType] = number_format($subjects->avg('gpa'), 2);
            }
        }

        return response()->json([
            'status' => true,
            'message' => 'Student result fetched successfully',
            'data' => [
                'student' => [
                    'name' => $student->first_name . ' ' . $student->last_name,
                    'current_class' => $student->class->name,
                    'roll_number' => $student->roll_number,
                ],
                'results' => $formattedResults,
                'overall_gpa' => $overallGPA, // GPA per class + exam type
            ]
        ]);
    }



    // public function createResultByTeacher(Request $request)
    // {
    //     $user = $request->user();


    //     $validated = $request->validate([
    //         'student_id' => 'required|exists:students,id',
    //         'class_id' => 'required|exists:classes,id',
    //         'exam_type' => 'nullable|string|max:255',
    //         'exam_date' => 'nullable',
    //         'results' => 'required|array',
    //         'results.*.subject_id' => 'required|exists:subjects,id',
    //         'results.*.marks_theory' => 'required|integer|min:0',
    //         'results.*.marks_practical' => 'required|integer|min:0',
    //     ]);

    //     $teacher = Teacher::with('subjects')->where('user_id', $user->id)->firstOrFail();
    //     // dd($teacher->subjects);
    //     $savedResults = [];

    //     foreach ($validated['results'] as $resultData) {
    //         $canAdd = true;

    //         // if ($teacher->class_teacher_of && $teacher->class_teacher_of == $validated['class_id']) {
    //         //     $canAdd = true;
    //         // }

    //         // if ($teacher->subjects->contains('id', $resultData['subject_id'])) {
    //         //     $canAdd = true;
    //         // }

    //         if (!$canAdd) {
    //             return response()->json([
    //                 'status' => false,
    //                 'message' => 'You are not authorized to add result for subject ' . $resultData['subject_id']
    //             ], 403);
    //         }

    //         $subject = \App\Models\Admin\Subject::findOrFail($resultData['subject_id']);
    //         $max_theory = $subject->theory_marks ?? 0;
    //         $max_practical = $subject->practical_marks ?? 0;

    //         $marks_obtained = $resultData['marks_theory'] + $resultData['marks_practical'];
    //         $max_marks = $max_theory + $max_practical;
    //         $gpa = $max_marks > 0 ? round(($marks_obtained / $max_marks) * 4, 2) : 0;

    //         $savedResults[] = Result::create([
    //             'student_id' => $validated['student_id'],
    //             'class_id' => $validated['class_id'],
    //             'subject_id' => $resultData['subject_id'],
    //             'teacher_id' => $teacher->id,
    //             'marks_theory' => $resultData['marks_theory'],
    //             'marks_practical' => $resultData['marks_practical'],
    //             'gpa' => $gpa,
    //             'exam_type' => $validated['exam_type'] ?? null,
    //             'exam_date' => $validated['exam_date'] ?? null,
    //         ]);
    //     }

    //     return response()->json([
    //         'status' => true,
    //         'message' => 'Results added successfully with GPA',
    //         'data' => $savedResults
    //     ], 201);
    // }





    // To view the result by teacher
    public function resultsByTeacher(Request $request, $domain, $teacherId)
    {
        $auth = $request->user();

        // load teacher record by teachers.id
        $teacher = Teacher::find($teacherId);
        if (!$teacher) {
            return response()->json([
                'status' => false,
                'message' => 'Teacher not found'
            ], 404);
        }

        // If caller is a teacher, allow only their own teacher record
        if ($auth->role === 'teacher') {
            $authTeacher = Teacher::where('user_id', $auth->id)->first();
            if (!$authTeacher || $authTeacher->id !== (int) $teacherId) {
                return response()->json([
                    'status' => false,
                    'message' => 'Forbidden'
                ], 403);
            }
        }

        // 1) Get all class-subject assignments for this teacher from pivot
        $assignments = DB::table('class_subject_teacher')
            ->where('teacher_id', $teacher->id)
            ->get(['class_id', 'subject_id']);

        // group subject ids by class id
        $grouped = $assignments->groupBy('class_id')->map(function ($rows) {
            return $rows->pluck('subject_id')->unique()->values()->all();
        });

        // Build query:
        $query = Result::with(['student', 'subject']);

        $query->where(function ($q) use ($teacher, $grouped) {
            // include results created by this teacher
            $q->where('teacher_id', $teacher->id);

            // include results for the class teacher class (all subjects)
            if ($teacher->class_teacher_of) {
                $q->orWhere('class_id', $teacher->class_teacher_of);
            }

            // include results for each (class, [subjectIds]) pair teacher teaches
            foreach ($grouped as $classId => $subjectIds) {
                // if subjectIds empty skip
                if (empty($subjectIds))
                    continue;

                $q->orWhere(function ($subq) use ($classId, $subjectIds) {
                    $subq->where('class_id', $classId)
                        ->whereIn('subject_id', $subjectIds);
                });
            }
        });

        // optionally order
        $results = $query->orderBy('class_id')->orderBy('student_id')->get();

        return response()->json([
            'status' => true,
            'data' => $results,
        ]);
    }




    // Get the result by the class For the admin
    public function classLedger($domain, $classId)
    {
        // Fetch all students in the class
        $students = Student::where('class_id', $classId)
            ->with(['results.subject', "class"]) // eager load results and subjects
            ->get();

        $ledger = [];

        $class = SchoolClass::find($classId);
        $className = $class->name;

        // dd($students->first()->results);


        foreach ($students as $student) {
            $totalMarks = 0;
            $maxMarks = 0;
            $subjectsData = [];

            foreach ($student->results as $result) {
                $theoryMarks = $result->marks_theory ?? 0;
                $practicalMarks = $result->marks_practical ?? 0;
                $subjectTotal = $theoryMarks + $practicalMarks;
                $subjectMax = ($result->subject->theory_marks ?? 0) + ($result->subject->practical_marks ?? 0);

                $totalMarks += $subjectTotal;
                $maxMarks += $subjectMax;
                $examTerm = $result->exam_type;

                $subjectsData[] = [
                    'subject_name' => $result->subject->name ?? 'N/A',
                    'marks_theory' => $theoryMarks,
                    'marks_practical' => $practicalMarks,
                    'total_marks' => $subjectTotal,
                    'max_marks' => $subjectMax,
                    "exam_type" => $examTerm
                ];
            }

            $percentage = $maxMarks > 0 ? round(($totalMarks / $maxMarks) * 100, 2) : 0;

            $ledger[] = [
                'student_id' => $student->id,
                'class' => $className,
                'student_name' => $student->first_name . ' ' . $student->last_name,
                'total_marks' => $totalMarks,
                'max_marks' => $maxMarks,
                'percentage' => $percentage,
                'subjects' => $subjectsData,
            ];
        }

        // Sort by total marks descending to calculate rank
        usort($ledger, function ($a, $b) {
            return $b['total_marks'] <=> $a['total_marks'];
        });

        // Assign rank
        foreach ($ledger as $index => &$student) {
            $student['rank'] = $index + 1;
        }

        return response()->json([
            'status' => true,
            'message' => 'Ledger fetched successfully for class ' . $classId,
            'data' => $ledger
        ], 200);
    }


    // Get the Individual Student Result by Student Id for Admin
    /**
     * Get results of a student by student ID
     */
    public function getStudentResultsById(Request $request, $domain, $studentId)
    {
        $user = $request->user();

        // Fetch the student
        $student = Student::with('class:id,name')->findOrFail($studentId);


        // Base query for results with relationships
        $query = Result::with(relations: [
            'subject:id,name,theory_marks,practical_marks',
            'class:id,name,class_code',
            'teacher:id,first_name,last_name'
        ])->where('student_id', $student->id);


        // Role-based access control
        if ($user->role === 'teacher') {
            $teacher = $user->teacher;
            $query->where(function ($q) use ($teacher) {
                $q->where('teacher_id', $teacher->id)
                    ->orWhere('class_id', $teacher->class_teacher_of_id);
            });
        } elseif ($user->role === 'student') {
            if ($user->student->id != $studentId) {
                return response()->json([
                    'status' => false,
                    'message' => 'Not allowed to view this student\'s results'
                ], 403);
            }
        } elseif ($user->role === 'parent') {
            $childIds = $user->parent->students()->pluck('id');
            if (!$childIds->contains($studentId)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Not allowed to view this student\'s results'
                ], 403);
            }
        }
        // Admin can view all results, no restrictions
        elseif ($user->role === 'admin') {
            // no filtering needed
        } else {
            return response()->json([
                'status' => false,
                'message' => 'Role not authorized'
            ], 403);
        }




        // dd($query);
        $results = Result::with(['subject', 'class', 'teacher'])
            ->where('student_id', $student->id)
            ->get()
            ->map(function ($result) {
                return [
                    'subject_name' => $result->subject->name ?? 'N/A',
                    'marks_theory' => $result->marks_theory,
                    'max_theory' => $result->subject->theory_marks ?? 0,
                    'marks_practical' => $result->marks_practical,
                    'max_practical' => $result->subject->practical_marks ?? 0,
                    'gpa' => $result->gpa,
                    'exam_type' => $result->exam_type,
                    'teacher_name' => $result->teacher->name ?? 'N/A',
                    'class_name' => $result->class->name ?? 'N/A',
                ];
            });





        return response()->json([
            'status' => true,
            'message' => 'Results fetched successfully for student ' . $student->first_name . ' ' . $student->last_name,
            'data' => [
                'student' => [
                    'id' => $student->id,
                    'name' => $student->first_name . ' ' . $student->last_name,
                    'class' => $student->class->name ?? 'N/A',
                    'roll_number' => $student->roll_number,
                ],
                'results' => $results,
            ]
        ], 200);
    }




    public function bulkStore(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'class_id' => 'required|exists:school_classes,id',
            'exam_type' => 'required|string|max:255',
            'exam_date' => 'required',
            'results' => 'required|array|min:1',
            'results.*.student_id' => 'required|exists:students,id',
            'results.*.subject_id' => 'required|exists:subjects,id',
            'results.*.marks_theory' => 'required|integer|min:0',
            'results.*.marks_practical' => 'required|integer|min:0',
        ]);

        $resultsData = [];

        foreach ($validated['results'] as $data) {
            $subject = Subject::find($data['subject_id']);

            $max_theory = $subject->theory_marks ?? 0;
            $max_practical = $subject->practical_marks ?? 0;
            $marks_obtained = $data['marks_theory'] + $data['marks_practical'];
            $max_marks = $max_theory + $max_practical;
            $gpa = $max_marks > 0 ? round(($marks_obtained / $max_marks) * 4, 2) : 0;

            $resultsData[] = [
                'student_id' => $data['student_id'],
                'class_id' => $validated['class_id'],
                'subject_id' => $data['subject_id'],
                'teacher_id' => $user->id ?? null,
                // 'teacher_id' => 25 ?? null,
                'marks_theory' => $data['marks_theory'],
                'marks_practical' => $data['marks_practical'],
                'gpa' => $gpa,
                'exam_type' => $validated['exam_type'],
                'exam_date' => $validated['exam_date'],
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        Result::insert($resultsData);

        return response()->json([
            'status' => true,
            'message' => 'Bulk results uploaded successfully!',
            'count' => count($resultsData),
        ], 201);
    }




    public function createResultByTeacher(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'student_id' => 'required|exists:students,id',
            'class_id' => 'required|exists:classes,id',
            'exam_type' => 'nullable|string|max:255',
            'exam_date' => 'nullable|date',

            'results' => 'required|array',
            'results.*.subject_id' => 'required|exists:subjects,id',
            'results.*.marks_theory' => 'required|integer|min:0',
            'results.*.marks_practical' => 'required|integer|min:0',

            // EXTRA ACTIVITY VALIDATION
            'results.*.activities' => 'nullable|array',
            'results.*.activities.*.activity_id' => 'required|exists:extra_curricular_activities,id',
            'results.*.activities.*.marks' => 'required|integer|min:0',
        ]);

        $teacher = Teacher::with('subjects')->where('user_id', $user->id)->firstOrFail();

        DB::beginTransaction();

        try {

            foreach ($validated['results'] as $resultData) {

                $existingResult = Result::where('student_id', $validated['student_id'])
                    ->where('class_id', $validated['class_id'])
                    ->where('subject_id', $resultData['subject_id'])
                    ->where('exam_type', $validated['exam_type'])
                    ->first();

                if ($existingResult) {
                    return response()->json([
                        'status' => false,
                        'message' => "Result already exists for this student, subject, and exam type."
                    ], 409); // 409 Conflict
                }

                // AUTHORIZATION CHECK
                // if (
                //     !$teacher->subjects->contains('id', $resultData['subject_id']) ||
                //     $teacher->class_teacher_of != $validated['class_id']
                // ) {
                //     return response()->json([
                //         'status' => false,
                //         'message' => 'Unauthorized to add marks for this subject'
                //     ], 403);
                // }

                // SUBJECT MARKS
                $subject = Subject::findOrFail($resultData['subject_id']);

                $maxSubjectMarks = ($subject->theory_marks ?? 0) + ($subject->practical_marks ?? 0);
                $obtainedSubjectMarks = $resultData['marks_theory'] + $resultData['marks_practical'];

                // CREATE RESULT
                $result = Result::create([
                    'student_id' => $validated['student_id'],
                    'class_id' => $validated['class_id'],
                    'subject_id' => $resultData['subject_id'],
                    'teacher_id' => $teacher->id,
                    'marks_theory' => $resultData['marks_theory'],
                    'marks_practical' => $resultData['marks_practical'],
                    'exam_type' => $validated['exam_type'] ?? null,
                    'exam_date' => $validated['exam_date'] ?? null,
                    'gpa' => 0 // UPDATE AFTER ACTIVITIES
                ]);

                $activityMarks = 0;
                $activityMaxMarks = 0;

                // SAVE ACTIVITY MARKS
                if (!empty($resultData['activities'])) {
                    foreach ($resultData['activities'] as $activityData) {

                        $activity = ExtraCurricularActivity::findOrFail($activityData['activity_id']);

                        ResultActivity::create([
                            'result_id' => $result->id,
                            'activity_id' => $activityData['activity_id'],
                            'marks' => $activityData['marks']
                        ]);

                        $activityMarks += $activityData['marks'];
                        $activityMaxMarks += $activity->full_marks ?? 0;
                    }
                }

                // GPA RE-CALCULATION
                $totalObtained = $obtainedSubjectMarks + $activityMarks;
                $totalMax = $maxSubjectMarks + $activityMaxMarks;

                $gpa = $totalMax > 0 ? round(($totalObtained / $totalMax) * 4, 2) : 0;

                $result->update(['gpa' => $gpa]);
            }

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Result with activities saved successfully'
            ], 201);

        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'status' => false,
                'message' => 'Failed to store result',
                'error' => $e->getMessage()
            ], 500);
        }
    }

}
