<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\AcademicYear;
use App\Models\Admin\ExtraCurricularActivity;
use App\Models\Admin\FinalResult;
use App\Models\Admin\Result;
use App\Models\Admin\ResultActivity;
use App\Models\Admin\ResultSetting;
use App\Models\Admin\SchoolClass;
use App\Models\Admin\Student;
use App\Models\Admin\Subject;
use App\Models\Admin\Teacher;
use App\Services\ResultCalculationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ResultController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, $domain)
    {
        $user = $request->user();

        // Base query with eager loading
        $query = Result::with([
            'student:id,first_name,last_name,class_id',
            'class:id,name,class_code',
            'subject:id,name,theory_marks,practical_marks,theory_pass_marks',
            'teacher:id,name'
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
    public function store(Request $request, $domain)
    {
        try {
            $user = Auth::user();
            $teacherId = Teacher::where('user_id', $user->id)->value('id');

            // Initialize ResultCalculationService
            $calculationService = new ResultCalculationService();

            // Get academic year from request
            $academicYearId = $request->input('academic_year_id');

            // Validate ResultSetting exists
            try {
                $resultSetting = $calculationService->getResultSetting($academicYearId);
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
                'academic_year_id' => 'nullable|exists:academic_years,id',
                'marks_theory' => 'required|numeric|min:0',
                'marks_practical' => 'required|numeric|min:0',
                'exam_type' => 'nullable|string|max:255',
                'exam_date' => 'nullable',
                'remarks' => 'nullable|string|max:1000',
            ]);

            // Get academic year
            $academicYearId = $validated['academic_year_id'] ?? null;
            if (!$academicYearId) {
                $currentYear = $calculationService->getCurrentAcademicYear();
                $academicYearId = $currentYear?->id;
            }

            if (!$academicYearId) {
                return response()->json([
                    'status' => false,
                    'message' => 'No active academic year found. Please create one first.'
                ], 400);
            }

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
                (float) $validated['marks_theory'],
                (float) $max_theory,
                (float) $validated['marks_practical'],
                (float) $max_practical,
                $resultSetting,
                (float) ($subject->theory_pass_marks ?? 0),
                (float) ($subject->practical_pass_marks ?? 0)
            );

            $result = Result::create([
                'student_id' => $validated['student_id'],
                'class_id' => $validated['class_id'],
                'subject_id' => $validated['subject_id'],
                'teacher_id' => $teacherId,
                'term_id' => $validated['term_id'],
                'academic_year_id' => $academicYearId,
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
            'teacher:id,name'
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
                (float) $result->marks_theory,
                (float) $max_theory,
                (float) $result->marks_practical + $activityMarks,
                (float) $max_practical + $activityMax,
                $resultSetting,
                (float) ($subject->theory_pass_marks ?? 0),
                (float) ($subject->practical_pass_marks ?? 0)
            );

            $result->gpa = $calculatedResult['gpa'];
            $result->percentage = $calculatedResult['percentage'];
        }

        $result->save();

        // Update final result
        $this->updateFinalResult($result->student_id, $result->class_id, $result->academic_year_id);

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
            'teacher:id,name'
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
            'teacher:id,name'
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

        // Initialize calculation service
        $calculationService = new ResultCalculationService();

        // fetch all results including past classes
        $results = Result::with([
            'subject:id,name,theory_marks,practical_marks,theory_pass_marks,practical_pass_marks',
            'activities.activity:id,activity_name,full_marks',
            'academicYear'
        ])
            ->where('student_id', $student->id)
            ->get()
            ->groupBy(['academic_year_id', 'class_id', 'exam_type']);

        $formattedResults = [];
        $overallMetrics = []; // store overall metrics per exam

        foreach ($results as $academicYearId => $classes) {
            $academicYear = AcademicYear::find($academicYearId);
            $academicYearName = $academicYear ? $academicYear->name : 'Unknown Year';
            $resultSetting = ResultSetting::where('academic_year_id', $academicYearId)->first();

            foreach ($classes as $classId => $exams) {
                $className = SchoolClass::find($classId)->name ?? 'Unknown Class';
                $key = "{$academicYearName} - {$className}";

                foreach ($exams as $examType => $subjects) {
                    $totalObtained = 0;
                    $totalMax = 0;

                    // Format individual subjects
                    $formattedResults[$key][$examType] = $subjects->map(function ($result) use (&$totalObtained, &$totalMax, $calculationService, $resultSetting) {

                        // Check if practical marks should be included for this term
                        $termId = $result->term_id;
                        $includePractical = ($termId && $resultSetting) ? $calculationService->shouldIncludePractical($termId, $resultSetting) : true;

                        $theoryFull = (float) ($result->subject->theory_marks ?? 0);
                        $practicalFull = $includePractical ? (float) ($result->subject->practical_marks ?? 0) : 0;

                        $theoryObtained = (float) $result->marks_theory;
                        $practicalObtained = $includePractical ? (float) $result->marks_practical : 0;

                        $totalObtained += ($theoryObtained + $practicalObtained);
                        $totalMax += ($theoryFull + $practicalFull);

                        // Check activities
                        $includeActivities = ($termId && $resultSetting) ? $calculationService->shouldIncludeActivities($termId, $resultSetting) : true;
                        $activities = [];
                        if ($includeActivities && $result->activities->isNotEmpty()) {
                            $activities = $result->activities->map(function ($act) {
                                return [
                                    'activity_name' => $act->activity->activity_name ?? 'Unknown',
                                    'full_marks' => $act->activity->full_marks ?? 0,
                                    'pass_marks' => $act->activity->pass_marks ?? 0,
                                    'marks_obtained' => $act->marks
                                ];
                            })->values();
                        }

                        return [
                            'subject_name' => $result->subject->name,
                            'marks_theory' => $theoryObtained,
                            'max_theory' => $theoryFull,
                            'marks_practical' => $practicalObtained,
                            'max_practical' => $practicalFull,
                            'total_obtained' => $theoryObtained + $practicalObtained,
                            'total_max' => $theoryFull + $practicalFull,
                            'gpa' => $result->gpa,
                            'exam_type' => $result->exam_type,
                            'remarks' => $result->remarks,
                            'activities' => $activities
                        ];
                    });

                    // Calculate overall metrics for this exam
                    $percentage = $totalMax > 0 ? round(($totalObtained / $totalMax) * 100, 2) : 0;
                    $gpa = $subjects->avg('gpa');

                    $overallMetrics[$key][$examType] = [
                        'gpa' => number_format($gpa, 2),
                        'percentage' => $percentage,
                        'grade' => $calculationService->getGradeFromPercentage($percentage),
                        'division' => $calculationService->getDivisionFromPercentage($percentage),
                    ];
                }
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
                'overall_metrics' => $overallMetrics,
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

        $academicYearId = $request->query('academic_year_id');
        if (!$academicYearId) {
            $calculationService = new ResultCalculationService();
            $currentYear = $calculationService->getCurrentAcademicYear();
            $academicYearId = $currentYear?->id;
        }

        $query->where(function ($q) use ($teacher, $grouped, $academicYearId) {
            // include results created by this teacher
            $q->where('teacher_id', $teacher->id)
                ->when($academicYearId, fn($sq) => $sq->where('academic_year_id', $academicYearId));

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
    public function classLedger(Request $request, $domain, $classId)
    {
        $academicYearId = $request->query('academic_year_id');
        if (!$academicYearId) {
            $calculationService = new ResultCalculationService();
            $currentYear = $calculationService->getCurrentAcademicYear();
            $academicYearId = $currentYear?->id;
        }

        // Fetch all students in the class
        $students = Student::select('id', 'first_name', 'last_name', 'class_id')
            ->where('class_id', $classId)
            ->with([
                'results' => function ($q) use ($academicYearId) {
                    $q->with('subject:id,name,theory_marks,practical_marks');
                    if ($academicYearId) {
                        $q->where('academic_year_id', $academicYearId);
                    }
                }
            ])
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
            $gpa = $calculationService->calculateGPA($totalMarks, $maxMarks);

            $ledger[] = [
                'student_id' => $student->id,
                'class' => $className,
                'student_name' => $student->first_name . ' ' . $student->last_name,
                'total_marks' => $totalMarks,
                'max_marks' => $maxMarks,
                'percentage' => $percentage,
                'gpa' => $gpa,
                'grade' => $calculationService->getGradeFromPercentage($percentage),
                'division' => $calculationService->getDivisionFromPercentage($percentage),
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

        // academic_year_id from request or current
        $academicYearId = $request->query('academic_year_id');
        $calculationService = new ResultCalculationService();
        if (!$academicYearId) {
            $currentYear = $calculationService->getCurrentAcademicYear();
            $academicYearId = $currentYear?->id;
        }

        // Base query for results with relationships
        $resultsQuery = Result::with([
            'subject:id,name,theory_marks,practical_marks,theory_pass_marks,practical_pass_marks',
            'activities.activity:id,activity_name,full_marks,pass_marks',
            'class:id,name',
            'teacher:id,name'
        ])
            ->where('student_id', $studentId)
            ->when($academicYearId, fn($q) => $q->where('academic_year_id', $academicYearId));

        // Role-based access control
        if ($user->role === 'teacher') {
            $teacher = $user->teacher;
            $resultsQuery->where(function ($q) use ($teacher) {
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

        $studentResults = $resultsQuery->get();

        if ($studentResults->isEmpty()) {
            return response()->json([
                'status' => true,
                'message' => 'No results found for student ' . $student->first_name . ' ' . $student->last_name,
                'data' => [
                    'student' => [
                        'id' => $student->id,
                        'name' => $student->first_name . ' ' . $student->last_name,
                        'class' => $student->class->name ?? 'N/A',
                        'roll_number' => $student->roll_number,
                    ],
                    'results' => [],
                ]
            ], 200);
        }

        // Try to get result setting, but don't fail if it doesn't exist
        $resultSetting = null;
        try {
            $resultSetting = $calculationService->getResultSetting($academicYearId);
        } catch (\Exception $e) {
            \Log::warning("Result setting not configured in getStudentResultsById: " . $e->getMessage());
        }

        // Group results by exam type
        $grouped = $studentResults->groupBy('exam_type');

        $formattedResults = $grouped->map(function ($studentTermResults, $examType) use ($studentId, $calculationService, $resultSetting, $academicYearId) {
            $firstItem = $studentTermResults->first();
            $classId = $firstItem->class_id;

            $studentTotalMarks = 0;
            $studentMaxMarks = 0;

            $subjects = $studentTermResults->map(function ($result) use (&$studentTotalMarks, &$studentMaxMarks, $calculationService, $resultSetting) {

                $theoryFull = (float) ($result->subject->theory_marks ?? 0);
                $theoryPass = (float) ($result->subject->theory_pass_marks ?? 0);

                // Check if practical marks should be included for this term
                $includePractical = ($result->term_id && $resultSetting) ? $calculationService->shouldIncludePractical($result->term_id, $resultSetting) : true;

                // Check if activities should be included for this term
                $includeActivities = ($result->term_id && $resultSetting) ? $calculationService->shouldIncludeActivities($result->term_id, $resultSetting) : true;

                $activities = [];
                $activitiesPassMarks = 0;
                if ($includeActivities && $result->activities->isNotEmpty()) {
                    $activities = $result->activities->map(function ($act) {
                        return [
                            'activity_name' => $act->activity->activity_name ?? 'Unknown',
                            'full_marks' => $act->activity->full_marks ?? 0,
                            'pass_marks' => $act->activity->pass_marks ?? 0,
                            'marks_obtained' => $act->marks
                        ];
                    })->values();

                    // Calculate sum of activities' pass marks
                    $activitiesPassMarks = $result->activities->sum(function ($act) {
                        return (float) ($act->activity->pass_marks ?? 0);
                    });
                }

                $practicalFull = $includePractical ? (float) ($result->subject->practical_marks ?? 0) : 0;
                // Use activities pass marks sum, fallback to subject's practical_pass_marks if no activities
                $practicalPass = $includePractical ? ($activitiesPassMarks > 0 ? $activitiesPassMarks : (float) ($result->subject->practical_pass_marks ?? 0)) : 0;
                $practicalObtained = $includePractical ? (float) $result->marks_practical : 0;

                $totalObtained = (float) ($result->marks_theory + $practicalObtained);
                $totalFull = $theoryFull + $practicalFull;

                $studentTotalMarks += $totalObtained;
                $studentMaxMarks += $totalFull;

                return [
                    'result_id' => $result->id,
                    'subject' => $result->subject->name,
                    'theory_full_mark' => $theoryFull,
                    'theory_pass_mark' => $theoryPass,
                    'practical_full_mark' => $practicalFull,
                    'practical_pass_mark' => $practicalPass,
                    'obtained_marks_theory' => (float) $result->marks_theory,
                    'obtained_marks_practical' => $practicalObtained,
                    'obtained_total_marks' => $totalObtained,
                    'gpa' => (float) $result->gpa,
                    'percentage' => (float) $result->percentage,
                    'exam_date' => $result->exam_date,
                    'remarks' => $result->remarks,
                    'activities' => $activities,
                    'teacher_name' => $result->teacher ? $result->teacher->name : 'N/A'
                ];
            });

            // Calculate percentage and GPA based on subjects the student has results for
            $percentage = $studentMaxMarks > 0 ? round(($studentTotalMarks / $studentMaxMarks) * 100, 2) : 0;
            $gpa = $calculationService->calculateGPA($studentTotalMarks, $studentMaxMarks);

            // Check if student passed all subjects for THIS specific exam
            $passCheck = $calculationService->checkStudentPassedTermSubjects($studentId, $classId, $examType, $academicYearId);
            $isPassed = $passCheck['all_passed'];

            // Division should be based on actual percentage, but if student failed any subject, show "Fail"
            $division = $isPassed
                ? $calculationService->getDivisionFromPercentage($percentage)
                : 'Fail';

            // Calculate rank for this student in this class and exam
            $rank = null;
            if ($isPassed) {
                // Fetch all results for this class and exam to calculate rank
                $allResultsInClass = Result::where('class_id', $classId)
                    ->where('exam_type', $examType)
                    ->where('academic_year_id', $academicYearId)
                    ->get()
                    ->groupBy('student_id');

                $studentScores = $allResultsInClass->filter(function ($items, $sid) use ($calculationService, $classId, $examType, $academicYearId) {
                    // Only include students who passed all subjects in this exam for ranking
                    $pc = $calculationService->checkStudentPassedTermSubjects($sid, $classId, $examType, $academicYearId);
                    return $pc['all_passed'];
                })->map(function ($items) {
                    return $items->avg('gpa');
                })->sortDesc();

                $r = 1;
                $c = 0;
                $prevScore = null;
                foreach ($studentScores as $sid => $score) {
                    $c++;
                    if ($prevScore !== null && $score < $prevScore) {
                        $r = $c;
                    }
                    if ($sid == $studentId) {
                        $rank = $r;
                        break;
                    }
                    $prevScore = $score;
                }
            }

            return [
                'student_id' => $studentId,
                'composite_id' => $studentId . '_' . $examType, // Unique key for frontend
                'exam_type' => $examType,

                'total_marks' => $studentTotalMarks,
                'max_marks' => $studentMaxMarks,
                'percentage' => $percentage,
                'gpa' => $gpa,
                'grade' => $calculationService->getGradeFromPercentage($percentage),
                'division' => $division,
                'is_pass' => $isPassed,
                'subjects' => $subjects,
                'ranks' => $isPassed
                    ? [
                        [
                            'exam_type' => $examType,
                            'rank' => $rank
                        ]
                    ]
                    : [],
                'exam_date' => $firstItem->exam_date,
            ];
        })->values();

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
                'results' => $formattedResults,
            ]
        ], 200);
    }



    public function bulkStore(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'class_id' => 'required|exists:school_classes,id',
            'academic_year_id' => 'nullable|exists:academic_years,id',
            'exam_type' => 'required|string|max:255',
            'exam_date' => 'required',
            'results' => 'required|array|min:1',
            'results.*.student_id' => 'required|exists:students,id',
            'results.*.subject_id' => 'required|exists:subjects,id',
            'results.*.marks_theory' => 'required|numeric|min:0',
            'results.*.marks_practical' => 'required|numeric|min:0',
        ]);

        $calculationService = new ResultCalculationService();
        $academicYearId = $validated['academic_year_id'] ?? null;
        if (!$academicYearId) {
            $currentYear = $calculationService->getCurrentAcademicYear();
            $academicYearId = $currentYear?->id;
        }

        if (!$academicYearId) {
            return response()->json(['status' => false, 'message' => 'No active academic year found.'], 400);
        }

        $resultsData = [];

        foreach ($validated['results'] as $data) {
            $subject = Subject::find($data['subject_id']);

            $max_theory = $subject->theory_marks ?? 0;
            $max_practical = $subject->practical_marks ?? 0;

            // Use ResultCalculationService for calculation
            $calculatedResult = $calculationService->calculateResult(
                new Result(),
                (float) $data['marks_theory'],
                (float) $max_theory,
                (float) $data['marks_practical'],
                (float) $max_practical,
                // Pass null for result setting to use simple calculation if no setting exists
                null,
                (float) ($subject->theory_pass_marks ?? 0),
                (float) ($subject->practical_pass_marks ?? 0)
            );

            $resultsData[] = [
                'student_id' => $data['student_id'],
                'class_id' => $validated['class_id'],
                'subject_id' => $data['subject_id'],
                'teacher_id' => $user->id ?? null,
                'term_id' => null,
                'academic_year_id' => $academicYearId,
                'marks_theory' => $data['marks_theory'],
                'marks_practical' => $data['marks_practical'],
                'gpa' => $calculatedResult['gpa'],
                'percentage' => $calculatedResult['percentage'],
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
            'academic_year_id' => 'nullable|exists:academic_years,id',
            'exam_type' => 'nullable|string|max:255',
            'exam_date' => 'nullable|date',
            'remarks' => 'nullable|string|max:1000',

            'students' => 'required|array',
            'students.*.student_id' => 'required|exists:students,id',
            'students.*.remarks' => 'nullable|string|max:1000',
            'students.*.results' => 'required|array',

            'students.*.results.*.subject_id' => 'required|exists:subjects,id',
            'students.*.results.*.marks_theory' => 'required|numeric|min:0',
            'students.*.results.*.marks_practical' => 'required|numeric|min:0',

            'students.*.results.*.activities' => 'nullable|array',
            'students.*.results.*.activities.*.activity_id' => 'required|exists:extra_curricular_activities,id',
            'students.*.results.*.activities.*.marks' => 'required|numeric|min:0',
        ]);

        $academicYearId = $validated['academic_year_id'] ?? null;
        if (!$academicYearId) {
            $currentYear = $calculationService->getCurrentAcademicYear();
            $academicYearId = $currentYear?->id;
        }

        if (!$academicYearId) {
            return response()->json(['status' => false, 'message' => 'No active academic year found.'], 400);
        }

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
                        ->where('academic_year_id', $academicYearId)
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

                    $theoryObtained = (float) $resultData['marks_theory'];
                    $theoryMax = (float) ($subject->theory_marks ?? 0);

                    // Check if practical marks should be included for this term
                    $includePractical = $calculationService->shouldIncludePractical($validated['term_id'], $resultSetting);

                    // Practical marks (includes activities if summed by frontend)
                    // Set to 0 if practical shouldn't be included for this term
                    $practicalObtained = $includePractical ? (float) $resultData['marks_practical'] : 0;
                    $practicalMax = $includePractical ? (float) ($subject->practical_marks ?? 0) : 0;

                    // Use ResultCalculationService for calculation
                    $calculatedResult = $calculationService->calculateResult(
                        new Result(),
                        $theoryObtained,
                        $theoryMax,
                        $practicalObtained,
                        $practicalMax,
                        $resultSetting,
                        (float) ($subject->theory_pass_marks ?? 0),
                        (float) ($subject->practical_pass_marks ?? 0)
                    );

                    // CREATE RESULT
                    // Store the actual practical marks received, but calculation uses conditional value
                    $result = Result::create([
                        'student_id' => $studentData['student_id'],
                        'class_id' => $validated['class_id'],
                        'subject_id' => $resultData['subject_id'],
                        'teacher_id' => $teacher->id,
                        'term_id' => $validated['term_id'],
                        'academic_year_id' => $academicYearId,
                        'marks_theory' => $resultData['marks_theory'],
                        'marks_practical' => $includePractical ? $resultData['marks_practical'] : 0, // Store 0 if not included
                        'exam_type' => $validated['exam_type'] ?? null,
                        'exam_date' => $validated['exam_date'] ?? null,
                        'gpa' => $calculatedResult['gpa'],
                        'percentage' => $calculatedResult['percentage'],
                        'remarks' => $resultData['remarks'] ?? $studentData['remarks'] ?? $validated['remarks'] ?? null,
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
                $this->updateFinalResult($studentData['student_id'], $validated['class_id'], $academicYearId);
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
            'academic_year_id' => 'nullable|exists:academic_years,id',
            'exam_type' => 'nullable|string|max:255',
        ]);

        $academicYearId = $request->query('academic_year_id');
        if (!$academicYearId) {
            $calculationService = new ResultCalculationService();
            $currentYear = $calculationService->getCurrentAcademicYear();
            $academicYearId = $currentYear?->id;
        }

        // Fetch results with subject, teacher, and activities
        $results = Result::with([
            'subject:id,name,theory_marks,practical_marks,theory_pass_marks',
            'teacher:id,name',
            'activities.activity:id,activity_name,full_marks'
        ])
            ->where('student_id', $studentId)
            ->where('class_id', $classId)
            ->when($academicYearId, fn($q) => $q->where('academic_year_id', $academicYearId))
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

        // Optional filtering by exam type or term_id
        $examType = $request->query('exam_type');
        $termId = $request->query('term_id');

        // Fetch class results
        $academicYearId = $request->query('academic_year_id');
        if (!$academicYearId) {
            $calculationService = new ResultCalculationService();
            $currentYear = $calculationService->getCurrentAcademicYear();
            $academicYearId = $currentYear?->id;
        }

        $results = Result::with([
            'student:id,first_name,last_name,roll_number',
            'subject:id,name,theory_marks,practical_marks,theory_pass_marks,practical_pass_marks',
            'activities.activity:id,activity_name,full_marks,pass_marks'
        ])
            ->where('class_id', $classId)
            ->when($academicYearId, fn($q) => $q->where('academic_year_id', $academicYearId))
            ->when($examType, function ($q) use ($examType) {
                $q->where('exam_type', $examType);
            })
            ->when($termId, function ($q) use ($termId) {
                $q->where('term_id', $termId);
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

        // Calculate Ranks per Exam Type (only for students who passed)
        $calculationService = new ResultCalculationService();

        // First, determine which students passed all subjects PER EXAM TYPE
        $passStatusMap = [];
        try {
            foreach ($results->groupBy('exam_type') as $examType => $examResults) {
                foreach ($examResults->groupBy('student_id') as $studentId => $studentExamResults) {
                    $passCheck = $calculationService->checkStudentPassedTermSubjects(
                        $studentId,
                        $classId,
                        $examType,
                        $academicYearId
                    );
                    $passStatusMap[$examType][$studentId] = $passCheck['all_passed'];
                }
            }
        } catch (\Exception $e) {
            \Log::warning("Failed to calculate pass status in getWholeClassResults: " . $e->getMessage());
            // Continue without pass status if there's an error
        }

        $ranksByExam = $results->groupBy('exam_type')->map(function ($examResults, $examType) use ($passStatusMap) {
            // Only include students who passed all subjects in this exam for ranking
            $studentScores = $examResults->groupBy('student_id')
                ->filter(function ($items, $studentId) use ($passStatusMap, $examType) {
                    return $passStatusMap[$examType][$studentId] ?? false;
                })
                ->map(function ($items) {
                    return $items->avg('gpa');
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

        // Try to get result setting, but don't fail if it doesn't exist
        $resultSetting = null;
        try {
            $resultSetting = $calculationService->getResultSetting($academicYearId);
        } catch (\Exception $e) {
            \Log::warning("Result setting not configured in getWholeClassResults: " . $e->getMessage());
            // Continue without result setting - will use defaults
        }

        // Group results by student AND exam type to prevent mixing terms on one marksheet
        // Fetch all subjects assigned to this class to calculate the correct total max marks
        $allClassSubjects = SchoolClass::with('subjects')->find($classId)->subjects;

        // Option to show only students with complete results (all subjects)
        // Default is false to show all students with any results
        $onlyComplete = $request->query('only_complete', false);

        $grouped = $results->groupBy(function ($item) {
            return $item->student_id . '_' . $item->exam_type;
        });

        // Apply filter only if requested
        if ($onlyComplete) {
            $grouped = $grouped->filter(function ($studentTermResults) use ($allClassSubjects) {
                // Only show result if student has marks for ALL subjects in the class
                return $studentTermResults->count() >= $allClassSubjects->count();
            });
        }

        $grouped = $grouped->map(function ($studentTermResults) use ($ranksByExam, $calculationService, $passStatusMap, $classId, $academicYearId, $resultSetting, $allClassSubjects) {

            $firstItem = $studentTermResults->first();
            $student = $firstItem->student;
            $examType = $firstItem->exam_type;
            $studentId = $firstItem->student_id;

            $studentTotalMarks = 0;
            $studentMaxMarks = 0;

            $subjects = $studentTermResults->map(function ($result) use (&$studentTotalMarks, &$studentMaxMarks, $calculationService, $resultSetting) {

                $activityMarks = $result->activities->sum('marks');

                $theoryFull = (float) ($result->subject->theory_marks ?? 0);
                $theoryPass = (float) ($result->subject->theory_pass_marks ?? 0);

                // Check if practical marks should be included for this term
                $includePractical = ($result->term_id && $resultSetting) ? $calculationService->shouldIncludePractical($result->term_id, $resultSetting) : true;

                // Check if activities should be included for this term
                $includeActivities = ($result->term_id && $resultSetting) ? $calculationService->shouldIncludeActivities($result->term_id, $resultSetting) : true;

                $activities = [];
                $activitiesPassMarks = 0;
                if ($includeActivities && $result->activities->isNotEmpty()) {
                    $activities = $result->activities->map(function ($act) {
                        return [
                            'activity_name' => $act->activity->activity_name ?? 'Unknown',
                            'full_marks' => $act->activity->full_marks ?? 0,
                            'pass_marks' => $act->activity->pass_marks ?? 0,
                            'marks_obtained' => $act->marks
                        ];
                    })->values();

                    // Calculate sum of activities' pass marks
                    $activitiesPassMarks = $result->activities->sum(function ($act) {
                        return (float) ($act->activity->pass_marks ?? 0);
                    });
                }

                $practicalFull = $includePractical ? (float) ($result->subject->practical_marks ?? 0) : 0;
                // Use activities pass marks sum, fallback to subject's practical_pass_marks if no activities
                $practicalPass = $includePractical ? ($activitiesPassMarks > 0 ? $activitiesPassMarks : (float) ($result->subject->practical_pass_marks ?? 0)) : 0;
                $practicalObtained = $includePractical ? (float) $result->marks_practical : 0;

                $totalObtained = (float) ($result->marks_theory + $practicalObtained);
                $totalFull = $theoryFull + $practicalFull;

                $studentTotalMarks += $totalObtained;
                $studentMaxMarks += $totalFull;

                return [
                    'result_id' => $result->id,
                    'subject' => $result->subject->name,
                    'theory_full_mark' => $theoryFull,
                    'theory_pass_mark' => $theoryPass,
                    'practical_full_mark' => $practicalFull,
                    'practical_pass_mark' => $practicalPass,
                    'obtained_marks_theory' => (float) $result->marks_theory,
                    'obtained_marks_practical' => $practicalObtained,
                    'obtained_total_marks' => $totalObtained,
                    'gpa' => $result->gpa,
                    'percentage' => $result->percentage,
                    'exam_date' => $result->exam_date,
                    'remarks' => $result->remarks,
                    'activities' => $activities,
                ];
            });

            // Calculate Class Total Max Marks based on ALL subjects assigned to the class
            $termId = $firstItem->term_id ?? null;
            $classTotalMax = 0;

            foreach ($allClassSubjects as $sub) {
                $subIncludePractical = ($termId && $resultSetting) ? $calculationService->shouldIncludePractical($termId, $resultSetting) : true;

                $classTotalMax += (float) $sub->theory_marks;
                if ($subIncludePractical) {
                    $classTotalMax += (float) $sub->practical_marks;
                }
            }

            // Calculate percentage based on subjects the student has results for (studentMaxMarks)
            // NOT based on all class subjects (classTotalMax)
            $percentage = $studentMaxMarks > 0 ? round(($studentTotalMarks / $studentMaxMarks) * 100, 2) : 0;
            $gpa = $calculationService->calculateGPA($studentTotalMarks, $studentMaxMarks);

            // Check if student passed all subjects for THIS specific exam
            $isPassed = $passStatusMap[$examType][$studentId] ?? false;

            // Division should be based on actual percentage, but if student failed any subject, show "Fail"
            $division = $isPassed
                ? $calculationService->getDivisionFromPercentage($percentage)
                : 'Fail';

            return [
                'student_id' => $student->id,
                'composite_id' => $student->id . '_' . $examType, // Unique key for frontend
                'exam_type' => $examType,
                'student_name' => $student->first_name . ' ' . $student->last_name,
                'roll_no' => $student->roll_number,
                'total_marks' => $studentTotalMarks,
                'max_marks' => $studentMaxMarks, // Use actual max marks of subjects student has results for
                'percentage' => $percentage,
                'gpa' => $gpa,
                'grade' => $calculationService->getGradeFromPercentage($percentage),
                'division' => $division,
                'is_pass' => $isPassed,
                'subjects' => $subjects,
                'ranks' => $isPassed
                    ? [
                        [
                            'exam_type' => $examType,
                            'rank' => $ranksByExam[$examType][$studentId] ?? null
                        ]
                    ]
                    : []
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

        // Get academic year
        $academicYearId = $request->query('academic_year_id');
        if (!$academicYearId) {
            $currentYear = $calculationService->getCurrentAcademicYear();
            $academicYearId = $currentYear?->id;
        }

        if (!$academicYearId) {
            return response()->json([
                'status' => false,
                'message' => 'No active academic year found.'
            ], 400);
        }

        // Get all terms from result setting
        $terms = $resultSetting->terms()->orderBy('id')->get();
        if ($terms->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No terms configured in result settings.'
            ], 400);
        }

        // Check if student has results for all terms
        $missingTerms = [];
        foreach ($terms as $term) {
            $hasResults = Result::where('student_id', $validated['student_id'])
                ->where('class_id', $validated['class_id'])
                ->where('term_id', $term->id)
                ->where('academic_year_id', $academicYearId)
                ->exists();

            if (!$hasResults) {
                $missingTerms[] = $term->name ?? "Term ID: {$term->id}";
            }
        }

        // If student has missing term results, return error with details
        if (!empty($missingTerms)) {
            $student = Student::find($validated['student_id']);
            return response()->json([
                'status' => false,
                'message' => 'Cannot generate final result. Student is missing results for one or more terms.',
                'reason' => 'Student must have results for all configured terms before final result can be generated.',
                'student' => [
                    'id' => $student->id,
                    'name' => $student->first_name . ' ' . $student->last_name,
                    'roll_number' => $student->roll_number
                ],
                'missing_terms' => $missingTerms,
                'required_terms' => $terms->pluck('name', 'id')->toArray()
            ], 400);
        }

        // Calculate weighted final result
        $finalResultData = $calculationService->calculateWeightedFinalResult(
            $validated['student_id'],
            $validated['class_id'],
            $resultSetting,
            $academicYearId
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
     * Stores final result in separate final_results table to avoid affecting term results
     */
    private function updateFinalResult($studentId, $classId, $academicYearId = null)
    {
        try {
            $calculationService = new ResultCalculationService();
            if (!$calculationService->validateResultSetting())
                return;

            $resultSetting = $calculationService->getResultSetting($academicYearId);

            // Only proceed if weighted
            if ($resultSetting->calculation_method !== 'weighted')
                return;

            // Calculate weighted final result
            $data = $calculationService->calculateWeightedFinalResult($studentId, $classId, $resultSetting, $academicYearId);

            if ($data && isset($data['subject_results'])) {
                // Store subject-wise final results in final_results table
                foreach ($data['subject_results'] as $subjectResult) {
                    FinalResult::updateOrCreate(
                        [
                            'student_id' => $studentId,
                            'class_id' => $classId,
                            'academic_year_id' => $academicYearId,
                            'subject_id' => $subjectResult['subject_id'],
                        ],
                        [
                            'final_gpa' => $subjectResult['weighted_gpa'],
                            'final_percentage' => $subjectResult['weighted_percentage'],
                            'final_theory_marks' => $subjectResult['obtained_marks_theory'] ?? null,
                            'final_practical_marks' => $subjectResult['obtained_marks_practical'] ?? null,
                            'final_grade' => $subjectResult['grade'] ?? null,
                            'final_division' => $subjectResult['division'] ?? null,
                            'is_passed' => $subjectResult['passed_nepal_criteria'] ?? false,
                            'result_type' => $data['result_type'],
                            'calculation_method' => 'weighted',
                        ]
                    );
                }

                // Store overall final result (without subject_id)
                FinalResult::updateOrCreate(
                    [
                        'student_id' => $studentId,
                        'class_id' => $classId,
                        'academic_year_id' => $academicYearId,
                        'subject_id' => null,
                    ],
                    [
                        'final_gpa' => $data['final_gpa'],
                        'final_percentage' => $data['final_percentage'],
                        'final_grade' => $data['final_grade'] ?? null,
                        'final_division' => $data['final_division'] ?? null,
                        'is_passed' => $data['nepal_passed_all_subjects'] ?? false,
                        'result_type' => $data['result_type'],
                        'calculation_method' => 'weighted',
                        'term_breakdown' => [
                            'total_weight' => $data['total_weight'] ?? null,
                            'passed_subjects' => $data['nepal_passed_subjects'] ?? [],
                            'failed_subjects' => $data['nepal_failed_subjects'] ?? [],
                        ],
                    ]
                );
            }
        } catch (\Exception $e) {
            // Silently fail or log, don't block the main request
            \Log::error('Failed to update final result: ' . $e->getMessage());
        }
    }

    /**
     * Generate final weighted result for the whole class
     * Stores results in final_results table to preserve term-wise results
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
        $academicYearId = $request->query('academic_year_id');
        if (!$academicYearId) {
            $currentYear = $calculationService->getCurrentAcademicYear();
            $academicYearId = $currentYear?->id;
        }

        $students = Student::where('class_id', $classId)->get();

        if ($students->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No students found in this class.'
            ], 404);
        }

        // Get all terms from result setting
        $terms = $resultSetting->terms()->orderBy('id')->get();
        if ($terms->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No terms configured in result settings.'
            ], 400);
        }

        // Check if all students have results for all terms
        $missingTermsData = [];
        foreach ($students as $student) {
            $missingTerms = [];
            foreach ($terms as $term) {
                $hasResults = Result::where('student_id', $student->id)
                    ->where('class_id', $classId)
                    ->where('term_id', $term->id)
                    ->where('academic_year_id', $academicYearId)
                    ->exists();

                if (!$hasResults) {
                    $missingTerms[] = $term->name ?? "Term ID: {$term->id}";
                }
            }

            if (!empty($missingTerms)) {
                $missingTermsData[] = [
                    'student_id' => $student->id,
                    'student_name' => $student->first_name . ' ' . $student->last_name,
                    'roll_number' => $student->roll_number,
                    'missing_terms' => $missingTerms
                ];
            }
        }

        // If any student has missing term results, return error with details
        if (!empty($missingTermsData)) {
            return response()->json([
                'status' => false,
                'message' => 'Cannot generate final results. Some students are missing results for one or more terms.',
                'reason' => 'All students must have results for all configured terms before final results can be generated.',
                'missing_data' => $missingTermsData,
                'total_affected_students' => count($missingTermsData),
                'total_students' => $students->count(),
                'required_terms' => $terms->pluck('name', 'id')->toArray()
            ], 400);
        }

        DB::beginTransaction();

        try {
            $successCount = 0;
            $errors = [];
            $results = [];
            $studentFinalScores = []; // For rank calculation

            foreach ($students as $student) {
                // Calculate weighted final result for THIS specific student
                $finalResultData = $calculationService->calculateWeightedFinalResult(
                    $student->id,
                    $classId,
                    $resultSetting,
                    $academicYearId
                );

                if ($finalResultData && isset($finalResultData['subject_results'])) {
                    // Store subject-wise final results in final_results table
                    foreach ($finalResultData['subject_results'] as $subjectResult) {
                        FinalResult::updateOrCreate(
                            [
                                'student_id' => $student->id,
                                'class_id' => $classId,
                                'academic_year_id' => $academicYearId,
                                'subject_id' => $subjectResult['subject_id'],
                            ],
                            [
                                'final_gpa' => $subjectResult['weighted_gpa'],
                                'final_percentage' => $subjectResult['weighted_percentage'],
                                'final_theory_marks' => $subjectResult['obtained_marks_theory'] ?? null,
                                'final_practical_marks' => $subjectResult['obtained_marks_practical'] ?? null,
                                'final_grade' => $subjectResult['grade'] ?? null,
                                'final_division' => $subjectResult['division'] ?? null,
                                'is_passed' => $subjectResult['passed_nepal_criteria'] ?? false,
                                'result_type' => $finalResultData['result_type'],
                                'calculation_method' => 'weighted',
                            ]
                        );
                    }

                    // Store overall final result (without subject_id)
                    FinalResult::updateOrCreate(
                        [
                            'student_id' => $student->id,
                            'class_id' => $classId,
                            'academic_year_id' => $academicYearId,
                            'subject_id' => null,
                        ],
                        [
                            'final_gpa' => $finalResultData['final_gpa'],
                            'final_percentage' => $finalResultData['final_percentage'],
                            'final_grade' => $finalResultData['final_grade'] ?? null,
                            'final_division' => $finalResultData['final_division'] ?? null,
                            'is_passed' => $finalResultData['nepal_passed_all_subjects'] ?? false,
                            'result_type' => $finalResultData['result_type'],
                            'calculation_method' => 'weighted',
                            'term_breakdown' => [
                                'total_weight' => $finalResultData['total_weight'] ?? null,
                                'passed_subjects' => $finalResultData['nepal_passed_subjects'] ?? [],
                                'failed_subjects' => $finalResultData['nepal_failed_subjects'] ?? [],
                            ],
                        ]
                    );

                    $successCount++;

                    // Track for ranking (only passed students get ranks)
                    if ($finalResultData['nepal_passed_all_subjects'] ?? false) {
                        $studentFinalScores[$student->id] = [
                            'gpa' => $finalResultData['final_gpa'],
                            'percentage' => $finalResultData['final_percentage'],
                        ];
                    }

                    // Store result details for response
                    $results[] = [
                        'student_id' => $student->id,
                        'student_name' => $student->first_name . ' ' . $student->last_name,
                        'roll_number' => $student->roll_number,
                        'final_result' => $finalResultData['final_result'],
                        'final_gpa' => $finalResultData['final_gpa'] ?? null,
                        'final_percentage' => $finalResultData['final_percentage'] ?? null,
                        'final_grade' => $finalResultData['final_grade'] ?? null,
                        'final_division' => $finalResultData['final_division'] ?? null,
                        'is_passed' => $finalResultData['nepal_passed_all_subjects'] ?? false,
                        'result_type' => $finalResultData['result_type'],
                        'subject_results' => $finalResultData['subject_results']
                    ];
                } else {
                    $errors[] = [
                        'student_id' => $student->id,
                        'student_name' => $student->first_name . ' ' . $student->last_name,
                        'error' => 'Incomplete term results or missing data'
                    ];
                }
            }

            // Calculate and update ranks for passed students
            $rankMap = []; // Store student_id => rank mapping
            if (!empty($studentFinalScores)) {
                // Sort by GPA (or percentage based on result_type) descending
                $sortKey = $resultSetting->result_type === 'percentage' ? 'percentage' : 'gpa';
                uasort($studentFinalScores, function ($a, $b) use ($sortKey) {
                    return $b[$sortKey] <=> $a[$sortKey];
                });

                $rank = 1;
                $prevScore = null;
                $count = 0;

                foreach ($studentFinalScores as $studentId => $scores) {
                    $count++;
                    $currentScore = $scores[$sortKey];

                    if ($prevScore !== $currentScore) {
                        $rank = $count;
                    }

                    // Store rank in map for response
                    $rankMap[$studentId] = $rank;

                    // Update rank in final_results table
                    FinalResult::where('student_id', $studentId)
                        ->where('class_id', $classId)
                        ->where('academic_year_id', $academicYearId)
                        ->whereNull('subject_id')
                        ->update(['rank' => $rank]);

                    $prevScore = $currentScore;
                }
            }

            // Update results array with ranks (only passed students get ranks)
            $results = array_map(function ($result) use ($rankMap) {
                $result['rank'] = $result['is_passed'] ? ($rankMap[$result['student_id']] ?? null) : null;
                return $result;
            }, $results);

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => "Final result generated for $successCount students.",
                'total_students' => $students->count(),
                'generated_count' => $successCount,
                'results' => $results,
                'errors' => $errors
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => false,
                'message' => 'Failed to generate final results',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get final results for a class
     */
    public function getClassFinalResults(Request $request, $domain, $classId)
    {
        $calculationService = new ResultCalculationService();

        $academicYearId = $request->query('academic_year_id');
        if (!$academicYearId) {
            $currentYear = $calculationService->getCurrentAcademicYear();
            $academicYearId = $currentYear?->id;
        }

        // Get overall final results (where subject_id is null)
        $finalResults = FinalResult::with(['student:id,first_name,last_name,roll_number', 'class:id,name'])
            ->where('class_id', $classId)
            ->where('academic_year_id', $academicYearId)
            ->whereNull('subject_id')
            ->orderBy('rank')
            ->get();

        if ($finalResults->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No final results found for this class. Please generate final results first.'
            ], 404);
        }

        $formattedResults = $finalResults->map(function ($result) use ($classId, $academicYearId) {
            // Get subject-wise final results for this student
            $subjectResults = FinalResult::with('subject:id,name,theory_marks,practical_marks')
                ->where('student_id', $result->student_id)
                ->where('class_id', $classId)
                ->where('academic_year_id', $academicYearId)
                ->whereNotNull('subject_id')
                ->get()
                ->map(function ($sr) {
                    return [
                        'subject_id' => $sr->subject_id,
                        'subject_name' => $sr->subject->name ?? 'Unknown',
                        'final_gpa' => $sr->final_gpa,
                        'final_percentage' => $sr->final_percentage,
                        'final_theory_marks' => $sr->final_theory_marks,
                        'final_practical_marks' => $sr->final_practical_marks,
                        'final_grade' => $sr->final_grade,
                        'is_passed' => $sr->is_passed,
                    ];
                });

            return [
                'student_id' => $result->student_id,
                'student_name' => $result->student ?
                    $result->student->first_name . ' ' . $result->student->last_name : 'Unknown',
                'roll_number' => $result->student->roll_number ?? null,
                'final_gpa' => $result->final_gpa,
                'final_percentage' => $result->final_percentage,
                'final_grade' => $result->final_grade,
                'final_division' => $result->final_division,
                'is_passed' => $result->is_passed,
                'rank' => $result->rank,
                'result_type' => $result->result_type,
                'term_breakdown' => $result->term_breakdown,
                'subject_results' => $subjectResults,
            ];
        });

        $class = SchoolClass::find($classId);

        return response()->json([
            'status' => true,
            'message' => 'Final results fetched successfully',
            'class_id' => $classId,
            'class_name' => $class->name ?? 'Unknown',
            'academic_year_id' => $academicYearId,
            'total_students' => $finalResults->count(),
            'passed_count' => $finalResults->where('is_passed', true)->count(),
            'failed_count' => $finalResults->where('is_passed', false)->count(),
            'data' => $formattedResults
        ]);
    }

    /**
     * Get final result for a specific student
     */
    public function getStudentFinalResult(Request $request, $domain, $studentId)
    {
        $calculationService = new ResultCalculationService();

        $academicYearId = $request->query('academic_year_id');
        if (!$academicYearId) {
            $currentYear = $calculationService->getCurrentAcademicYear();
            $academicYearId = $currentYear?->id;
        }

        $student = Student::with('class:id,name')->findOrFail($studentId);

        // Get overall final result
        $finalResult = FinalResult::where('student_id', $studentId)
            ->where('academic_year_id', $academicYearId)
            ->whereNull('subject_id')
            ->first();

        if (!$finalResult) {
            return response()->json([
                'status' => false,
                'message' => 'No final result found for this student. Final results may not have been generated yet.'
            ], 404);
        }

        // Get subject-wise final results
        $subjectResults = FinalResult::with('subject:id,name,theory_marks,practical_marks,theory_pass_marks,practical_pass_marks')
            ->where('student_id', $studentId)
            ->where('class_id', $finalResult->class_id)
            ->where('academic_year_id', $academicYearId)
            ->whereNotNull('subject_id')
            ->get()
            ->map(function ($sr) {
                return [
                    'subject_id' => $sr->subject_id,
                    'subject_name' => $sr->subject->name ?? 'Unknown',
                    'theory_full_marks' => $sr->subject->theory_marks ?? 0,
                    'practical_full_marks' => $sr->subject->practical_marks ?? 0,
                    'theory_pass_marks' => $sr->subject->theory_pass_marks ?? 0,
                    'practical_pass_marks' => $sr->subject->practical_pass_marks ?? 0,
                    'final_theory_marks' => $sr->final_theory_marks,
                    'final_practical_marks' => $sr->final_practical_marks,
                    'final_gpa' => $sr->final_gpa,
                    'final_percentage' => $sr->final_percentage,
                    'final_grade' => $sr->final_grade,
                    'is_passed' => $sr->is_passed,
                ];
            });

        return response()->json([
            'status' => true,
            'message' => 'Final result fetched successfully',
            'data' => [
                'student' => [
                    'id' => $student->id,
                    'name' => $student->first_name . ' ' . $student->last_name,
                    'roll_number' => $student->roll_number,
                    'class' => $student->class->name ?? 'N/A',
                ],
                'final_result' => [
                    'final_gpa' => $finalResult->final_gpa,
                    'final_percentage' => $finalResult->final_percentage,
                    'final_grade' => $finalResult->final_grade,
                    'final_division' => $finalResult->final_division,
                    'is_passed' => $finalResult->is_passed,
                    'rank' => $finalResult->rank,
                    'result_type' => $finalResult->result_type,
                    'calculation_method' => $finalResult->calculation_method,
                    'term_breakdown' => $finalResult->term_breakdown,
                ],
                'subject_results' => $subjectResults,
            ]
        ]);
    }
}

