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
use App\Services\ResultCalculationService;
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
            'subject:id,name,theory_marks,practical_marks,theory_pass_marks',
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
            $teacherId = Teacher::where('user_id', $user->id)->value('id');
            
            // Initialize ResultCalculationService
            $calculationService = new ResultCalculationService();
            
            // Validate ResultSetting exists
            try {
                $resultSetting = $calculationService->getResultSetting();
            } catch (\Exception $e) {
                return response()->json([
                    'status' => false,
                    'message' => $e->getMessage()
                ], 400);
            }

            $validated = $request->validate([
                'student_id' => 'required|exists:students,id',
                'class_id' => 'required|exists:classes,id',
                'subject_id' => 'required|exists:subjects,id',
                'term_id' => 'required|exists:terms,id',
                'marks_theory' => 'required|numeric|min:0',
                'marks_practical' => 'required|numeric|min:0',
                'exam_type' => 'nullable|string|max:255',
                'exam_date' => 'nullable',
                'remarks' => 'nullable|string|max:1000',
            ]);
            
            // Validate term_id exists in ResultSetting
            if (!$calculationService->validateTerm($validated['term_id'], $resultSetting)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid term_id. Term does not exist in Result Setting.'
                ], 400);
            }

            $subject = Subject::findOrFail($validated['subject_id']);
            $max_theory = $subject->theory_marks ?? 0;
            $max_practical = $subject->practical_marks ?? 0;

            $marks_obtained = $validated['marks_theory'] + $validated['marks_practical'];
            $max_marks = $max_theory + $max_practical;
            
            // Use ResultCalculationService for calculation
            $calculatedResult = $calculationService->calculateResult(
                new Result(),
                $marks_obtained,
                $max_marks,
                $resultSetting
            );

            $result = Result::create([
                'student_id' => $validated['student_id'],
                'class_id' => $validated['class_id'],
                'subject_id' => $validated['subject_id'],
                'teacher_id' => $teacherId,
                'term_id' => $validated['term_id'],
                'marks_theory' => $validated['marks_theory'],
                'marks_practical' => $validated['marks_practical'],
                'gpa' => $calculatedResult['gpa'],
                'percentage' => $calculatedResult['percentage'],
                'exam_type' => $validated['exam_type'] ?? null,
                'exam_date' => $validated['exam_date'] ?? null,
                'remarks' => $validated['remarks'] ?? null,
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
            'subject:id,name,theory_marks,practical_marks,theory_pass_marks',
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

        /** @var Result $result */
        $result = Result::findOrFail($id);
        
        // Initialize ResultCalculationService
        $calculationService = new ResultCalculationService();
        
        // Get ResultSetting
        try {
            $resultSetting = $calculationService->getResultSetting();
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 400);
        }

        $validated = $request->validate([
            'marks_theory' => 'sometimes|numeric|min:0',
            'marks_practical' => 'sometimes|numeric|min:0',
            'exam_type' => 'nullable|string|max:255',
            'exam_date' => 'nullable',
            'remarks' => 'nullable|string|max:1000',
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
        
        // Update exam date if provided
        if (isset($validated['exam_date'])) {
            $result->exam_date = $validated['exam_date'];
        }
        
        // Update remark if provided
        if (isset($validated['remarks'])) {
            $result->remarks = $validated['remarks'];
        }

        // Recalculate if marks changed
        if (isset($validated['marks_theory']) || isset($validated['marks_practical'])) {
            $subject = $result->subject;
            $max_theory = $subject->theory_marks ?? 0;
            $max_practical = $subject->practical_marks ?? 0;
            
            // Get activity marks
            $activityMarks = $result->activities->sum('marks');
            $activityMax = $result->activities->sum(fn($a) => $a->activity->full_marks ?? 0);
            
            $marks_obtained = $result->marks_theory + $result->marks_practical + $activityMarks;
            $max_marks = $max_theory + $max_practical + $activityMax;
            
            // Use ResultCalculationService for recalculation
            $calculatedResult = $calculationService->calculateResult(
                $result,
                $marks_obtained,
                $max_marks,
                $resultSetting
            );
            
            $result->gpa = $calculatedResult['gpa'];
            $result->percentage = $calculatedResult['percentage'];
        }

        $result->save();
        
        // Update final result
        $this->updateFinalResult($result->student_id, $result->class_id);

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
            'subject:id,name,theory_marks,practical_marks,theory_pass_marks',
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
            'subject:id,name,theory_marks,practical_marks,theory_pass_marks',
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



        try {
            // find student based on user_id
            $student = Student::with('class:id,name')
                ->where('user_id', $user->id)
                ->firstOrFail();

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Student record not found for the logged in user.'
            ], 404);
        }

        // fetch all results including past classes
        $results = Result::with([
            'subject:id,name,theory_marks,practical_marks,theory_pass_marks',
            'activities.activity:id,activity_name,full_marks'
        ])
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
                    $activityMarks = $result->activities->sum('marks');
                    $activityMaxMarks = $result->activities->sum(fn($a) => $a->activity->full_marks ?? 0);

                    return [
                        'subject_name' => $result->subject->name,
                        'marks_theory' => $result->marks_theory,
                        'max_theory' => $result->subject->theory_marks,
                        'marks_practical' => $result->marks_practical,
                        'max_practical' => $result->subject->practical_marks,
                        'activity_marks' => $activityMarks,
                        'activity_max_marks' => $activityMaxMarks,
                        'total_obtained' => $result->marks_theory + $result->marks_practical + $activityMarks,
                        'total_max' => ($result->subject->theory_marks ?? 0) + ($result->subject->practical_marks ?? 0) + $activityMaxMarks,
                        'gpa' => $result->gpa,
                        'exam_type' => $result->exam_type,
                        'activities' => $result->activities->map(fn($a) => [
                            'activity_name' => $a->activity->activity_name,
                            'marks_obtained' => $a->marks,
                            'full_marks' => $a->activity->full_marks
                        ])
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
    //         'results.*.marks_theory' => 'required|numeric|min:0',
    //         'results.*.marks_practical' => 'required|numeric|min:0',
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
            'subject:id,name,theory_marks,practical_marks,theory_pass_marks',
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
                    'percentage' => $result->percentage,
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
            'results.*.marks_theory' => 'required|numeric|min:0',
            'results.*.marks_practical' => 'required|numeric|min:0',
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


    public function createClassResultByTeacher(Request $request)
    {
        $user = $request->user();
        
        // Initialize ResultCalculationService
        $calculationService = new ResultCalculationService();
        
        // Validate ResultSetting exists
        try {
            $resultSetting = $calculationService->getResultSetting();
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 400);
        }

        $validated = $request->validate([
            'class_id' => 'required|exists:classes,id',
            'term_id' => 'required|exists:terms,id',
            'exam_type' => 'nullable|string|max:255',
            'exam_date' => 'nullable|date',
            'remarks'=>'nullable|string|max:1000',

            'students' => 'required|array',
            'students.*.student_id' => 'required|exists:students,id',
            'students.*.results' => 'required|array',

            'students.*.results.*.subject_id' => 'required|exists:subjects,id',
            'students.*.results.*.marks_theory' => 'required|numeric|min:0',
            'students.*.results.*.marks_practical' => 'required|numeric|min:0',

            'students.*.results.*.activities' => 'nullable|array',
            'students.*.results.*.activities.*.activity_id' => 'required|exists:extra_curricular_activities,id',
            'students.*.results.*.activities.*.marks' => 'required|numeric|min:0',
        ]);
        
        // Validate term_id exists in ResultSetting
        if (!$calculationService->validateTerm($validated['term_id'], $resultSetting)) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid term_id. Term does not exist in Result Setting.'
            ], 400);
        }
        
        // Check if activities are allowed for this term
        $canAddActivities = $calculationService->canAddActivities($validated['term_id'], $resultSetting);

        $teacher = Teacher::with('subjects')
            ->where('user_id', $user->id)->firstOrFail();

        DB::beginTransaction();

        try {

            foreach ($validated['students'] as $studentData) {

                foreach ($studentData['results'] as $resultData) {

                    // Prevent duplicate entry
                    $exists = Result::where('student_id', $studentData['student_id'])
                        ->where('class_id', $validated['class_id'])
                        ->where('subject_id', $resultData['subject_id'])
                        ->where('term_id', $validated['term_id'])
                        ->exists();


                    if ($exists) {
                        throw new \Exception("Result already exists for some students and subjects in this term.");
                    }
                    
                    // Validate activities if provided
                    if (!empty($resultData['activities']) && !$canAddActivities) {
                        throw new \Exception(
                            "Activities cannot be added for this term. " .
                            ($resultSetting->evaluation_per_term 
                                ? "Activities are allowed for all terms." 
                                : "Activities can only be added for the last term exam.")
                        );
                    }

                    // SUBJECT MARKS
                    $subject = Subject::findOrFail($resultData['subject_id']);

                    $maxMarks = ($subject->theory_marks ?? 0) + ($subject->practical_marks ?? 0);
                    $obtained = $resultData['marks_theory'] + $resultData['marks_practical'];

                    $activityMarks = 0;
                    $activityMax = 0;

                    // Calculate activity marks if provided
                    if (!empty($resultData['activities'])) {
                        foreach ($resultData['activities'] as $activityData) {
                            $activity = ExtraCurricularActivity::where('id', $activityData['activity_id'])
                                ->where(function ($q) use ($validated) {
                                    $q->where('class_id', $validated['class_id'])
                                        ->orWhereNull('class_id');
                                })
                                ->firstOrFail();

                            $activityMarks += $activityData['marks'];
                            $activityMax += $activity->full_marks ?? 0;
                        }
                    }

                    // Calculate total
                    $total = $obtained + $activityMarks;
                    $totalMax = $maxMarks + $activityMax;

                    // Use ResultCalculationService for calculation
                    $calculatedResult = $calculationService->calculateResult(
                        new Result(),
                        $total,
                        $totalMax,
                        $resultSetting
                    );

                    // CREATE RESULT
                    $result = Result::create([
                        'student_id' => $studentData['student_id'],
                        'class_id' => $validated['class_id'],
                        'subject_id' => $resultData['subject_id'],
                        'teacher_id' => $teacher->id,
                        'term_id' => $validated['term_id'],
                        'marks_theory' => $resultData['marks_theory'],
                        'marks_practical' => $resultData['marks_practical'],
                        'exam_type' => $validated['exam_type'] ?? null,
                        'exam_date' => $validated['exam_date'] ?? null,
                        'gpa' => $calculatedResult['gpa'],
                        'percentage' => $calculatedResult['percentage'],
                        'remarks' => $validated['remarks'] ?? null,
                    ]);


                    // SAVE ACTIVITIES
                    if (!empty($resultData['activities'])) {
                        foreach ($resultData['activities'] as $activityData) {

                            $activity = ExtraCurricularActivity::where('id', $activityData['activity_id'])
                                ->where(function ($q) use ($validated) {
                                    $q->where('class_id', $validated['class_id'])
                                        ->orWhereNull('class_id');
                                })
                                ->firstOrFail();

                            ResultActivity::create([
                                'result_id' => $result->id,
                                'activity_id' => $activityData['activity_id'],
                                'marks' => $activityData['marks']
                            ]);
                        }
                    }
                }
            }


            
            // Update final results for all affected students
            foreach ($validated['students'] as $studentData) {
                $this->updateFinalResult($studentData['student_id'], $validated['class_id']);
            }

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Class results saved successfully 🎉'
            ], 201);

        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'status' => false,
                'message' => 'Failed to save class results',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getStudentResults(Request $request, $domain, $studentId, $classId, $examType = null)
    {
        // Validate input
        $request->validate([
            'student_id' => 'exists:students,id',
            'class_id' => 'exists:classes,id',
            'exam_type' => 'nullable|string|max:255',
        ]);

        // Fetch results with subject, teacher, and activities
        $results = Result::with([
            'subject:id,name,theory_marks,practical_marks,theory_pass_marks',
            'teacher:id,name',
            'activities.activity:id,activity_name,full_marks'
        ])
            ->where('student_id', $studentId)
            ->where('class_id', $classId)
            ->when($examType, fn($q) => $q->where('exam_type', $examType))
            ->get();

        if ($results->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No results found for this student.'
            ], 404);
        }

        // Transform results
        $data = $results->map(function ($result) {
            $activityMarks = $result->activities->sum('marks');
            $activityMaxMarks = $result->activities->sum(fn($a) => $a->activity->full_marks ?? 0);

            return [
                'result_id' => $result->id,
                'subject' => $result->subject->name,
                'marks_theory' => $result->marks_theory,
                'marks_practical' => $result->marks_practical,
                'total_marks_obtained' => $result->marks_theory + $result->marks_practical + $activityMarks,
                'total_max_marks' => ($result->subject->theory_marks ?? 0) + ($result->subject->practical_marks ?? 0) + $activityMaxMarks,
                'gpa' => $result->gpa,
                'percentage' => $result->percentage,
                'final_result' => $result->final_result,
                'exam_type' => $result->exam_type,
                'exam_date' => $result->exam_date,
                'activities' => $result->activities->map(fn($a) => [
                    'activity_name' => $a->activity->activity_name,
                    'marks_obtained' => $a->marks,
                    'full_marks' => $a->activity->full_marks
                ])
            ];
        });

        return response()->json([
            'status' => true,
            'student_id' => $studentId,
            'class_id' => $classId,
            'exam_type' => $examType,
            'results' => $data
        ]);
    }


    public function getWholeClassResults(Request $request, $domain, $classId)
    {
        // Validate class
        if (!SchoolClass::where('id', $classId)->exists()) {
            return response()->json([
                'status' => false,
                'message' => 'Class not found'
            ], 404);
        }

        // Optional filtering by exam type
        $examType = $request->query('exam_type');

        // Fetch class results
        $results = Result::with([
            'student:id,first_name,last_name,roll_number',
            'subject:id,name,theory_marks,practical_marks,theory_pass_marks',
            'activities.activity:id,activity_name,full_marks,pass_marks'
        ])
            ->where('class_id', $classId)
            ->when($examType, function ($q) use ($examType) {
                $q->where('exam_type', $examType);


            })
            ->orderBy('student_id')
            ->get();

        $examDate = $results->first()->exam_date ?? null;

        if ($results->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No results found for this class'
            ], 404);
        }

        // Calculate Ranks per Exam Type
        $ranksByExam = $results->groupBy('exam_type')->map(function ($examResults) {
            $studentScores = $examResults->groupBy('student_id')->map(function ($items) {
                return $items->sum(function ($result) {
                    return $result->marks_theory + $result->marks_practical + $result->activities->sum('marks');
                });
            })->sortDesc();

            $rank = 1;
            $ranks = [];
            $prevScore = null;
            $count = 0;

            foreach ($studentScores as $studentId => $score) {
                $count++;
                if ($prevScore !== $score) {
                    $rank = $count;
                }
                $ranks[$studentId] = $rank;
                $prevScore = $score;
            }
            return $ranks;
        });

        // Group results by student
        $grouped = $results->groupBy('student_id')->map(function ($studentResults, $studentId) use ($ranksByExam) {

            $student = $studentResults->first()->student;

            $subjects = $studentResults->map(function ($result) {

                $activityMarks = $result->activities->sum('marks');
                $activityMaxMarks = $result->activities->sum(fn($a) => $a->activity->full_marks ?? 0);

                return [
                    'result_id' => $result->id,
                    'subject' => $result->subject->name,
                    'theory_pass_marks' => $result->subject->theory_pass_marks,
                    'practical_pass_marks' => $result->activities->sum(fn($a) => $a->activity->pass_marks ?? 0),
                    'obtained_marks_theory' => $result->marks_theory,
                    'obtained_marks_practical' => $result->marks_practical,
                    'obtained_activity_marks' => $activityMarks,
                    'remarks' => $result->remarks,
                    'obtained_total_marks' => $result->marks_theory + $result->marks_practical,  // + $activityMarks, 
                    'full_marks_theory' =>
                        ($result->subject->theory_marks ?? 0),
                    'full_marks_practical' => ($result->subject->practical_marks ?? 0),
                    'gpa' => $result->gpa,
                    'gpa' => $result->gpa,
                    'percentage' => $result->percentage,
                    'final_result' => $result->final_result,
                    'exam_date' => $result->exam_date,
                    'activities' => $result->activities->map(fn($a) => [
                        'activity_name' => $a->activity->activity_name,
                        'marks_obtained' => $a->marks,
                        'full_marks' => $a->activity->full_marks,
                        'pass_marks' => $a->activity->pass_marks
                    ])
                ];
            });

            return [
                'student_id' => $student->id,
                'student_name' => $student->first_name . ' ' . $student->last_name,
                'roll_no' => $student->roll_number,
                'subjects' => $subjects,
                'ranks' => $studentResults->pluck('exam_type')->unique()->map(function ($examType) use ($ranksByExam, $studentId) {
                    return [
                        'exam_type' => $examType,
                        'rank' => $ranksByExam[$examType][$studentId] ?? null
                    ];
                })->values()
            ];
        })->values();

        return response()->json([
            'status' => true,
            'class_id' => $classId,
            'exam_type' => $examType,
            'exam_date' => $examDate,
            'students' => $grouped
        ]);
    }

    /**
     * Generate final weighted result for a student
     * Only applicable when calculation_method is 'weighted'
     */
    public function generateFinalResult(Request $request, $domain)
    {
        $validated = $request->validate([
            'student_id' => 'required|exists:students,id',
            'class_id' => 'required|exists:classes,id',
        ]);

        // Initialize ResultCalculationService
        $calculationService = new ResultCalculationService();

        // Get ResultSetting
        try {
            $resultSetting = $calculationService->getResultSetting();
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 400);
        }

        // Check if calculation method is weighted
        if ($resultSetting->calculation_method !== 'weighted') {
            return response()->json([
                'status' => false,
                'message' => 'Final result generation is only available for weighted calculation method.'
            ], 400);
        }


        // Calculate weighted final result
        $finalResultData = $calculationService->calculateWeightedFinalResult(
            $validated['student_id'],
            $validated['class_id'],
            $resultSetting
        );

        if (!$finalResultData) {
            return response()->json([
                'status' => false,
                'message' => 'Cannot calculate final result. Ensure all term results are available.'
            ], 400);
        }

        // Get student info
        $student = Student::with('class')->findOrFail($validated['student_id']);

        return response()->json([
            'status' => true,
            'message' => 'Final result calculated successfully',
            'data' => [
                'student' => [
                    'id' => $student->id,
                    'name' => $student->first_name . ' ' . $student->last_name,
                    'class' => $student->class->name ?? 'N/A',
                    'roll_number' => $student->roll_number,
                ],
                'final_result' => $finalResultData
            ]
        ]);
    }

    /**
     * Helper to update final result for a student
     */
    private function updateFinalResult($studentId, $classId)
    {
        try {
            $calculationService = new ResultCalculationService();
            if (!$calculationService->validateResultSetting()) return;
            
            $resultSetting = $calculationService->getResultSetting();
            
            // Only proceed if weighted
            if ($resultSetting->calculation_method !== 'weighted') return;

            // Calculate
            $data = $calculationService->calculateWeightedFinalResult($studentId, $classId, $resultSetting);

            if ($data && isset($data['final_result'])) {
                // Update all results for this student/class with the final result
                Result::where('student_id', $studentId)
                    ->where('class_id', $classId)
                    ->update(['final_result' => $data['final_result']]);
            }
        } catch (\Exception $e) {
            // Silently fail or log, don't block the main request
            \Log::error('Failed to update final result: ' . $e->getMessage());
        }
    }

    /**
     * Generate final weighted result for the whole class
     */
    public function generateClassFinalResult(Request $request, $domain, $classId)
    {
        // Initialize ResultCalculationService
        $calculationService = new ResultCalculationService();

        // Get ResultSetting
        try {
            $resultSetting = $calculationService->getResultSetting();
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 400);
        }

        // Check if calculation method is weighted
        if ($resultSetting->calculation_method !== 'weighted') {
            return response()->json([
                'status' => false,
                'message' => 'Class final result generation is only available for weighted calculation method.'
            ], 400);
        }

        // Fetch all students in the class with their names
        $students = Student::where('class_id', $classId)->get();

        if ($students->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No students found in this class.'
            ], 404);
        }

        $successCount = 0;
        $errors = [];
        $results = [];

        foreach ($students as $student) {
            // Calculate weighted final result for THIS specific student
            $finalResultData = $calculationService->calculateWeightedFinalResult(
                $student->id,
                $classId,
                $resultSetting
            );

            if ($finalResultData && isset($finalResultData['final_result'])) {
                 // Update all results for THIS student/class with THEIR final result
                 $updated = Result::where('student_id', $student->id)
                    ->where('class_id', $classId)
                    ->update(['final_result' => $finalResultData['final_result']]);
                
                $successCount++;
                
                // Store result details for response
                $results[] = [
                    'student_id' => $student->id,
                    'student_name' => $student->first_name . ' ' . $student->last_name,
                    'final_result' => $finalResultData['final_result'],
                    'result_type' => $finalResultData['result_type'],
                    'updated_records' => $updated
                ];
            } else {
                $errors[] = [
                    'student_id' => $student->id,
                    'student_name' => $student->first_name . ' ' . $student->last_name,
                    'error' => 'Incomplete term results or missing data'
                ];
            }
        }

        return response()->json([
            'status' => true,
            'message' => "Final result generated for $successCount students.",
            'total_students' => $students->count(),
            'generated_count' => $successCount,
            'results' => $results,
            'errors' => $errors
        ]);
    }
}
