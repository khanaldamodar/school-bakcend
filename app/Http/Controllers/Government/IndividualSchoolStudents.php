<?php

namespace App\Http\Controllers\Government;

use App\Http\Controllers\Controller;
use App\Models\Admin\Result;
use App\Models\Admin\Student;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class IndividualSchoolStudents extends Controller
{
    public function getAllStudents(Request $request, $schoolId)
    {
        $tenant = Tenant::findOrFail($schoolId);
        tenancy()->initialize($tenant);
        
        $query = Student::with('class');
        
        if ($request->has('academic_year_id')) {
            // Assuming academic year filtering is relevant for students via result or enrollment. 
            // If Student model doesn't have academic_year directly, this might be skipped or require join.
            // For now, returning all students as per original logic but keeping method signature ready.
        }

        $students = $query->get();


        if (!$tenant) {
            return response()->json([
                "status" => false,
                "message" => "There is no school with given id",
            ], 404);
        }

        if ($students->isEmpty()) {
            return response()->json([
                "status" => false,
                "message" => "No student found in this school"
            ], 404);
        }

        return response()->json([
            "status" => true,
            "message" => "Student Fetched Success",
            "students" => $students
        ], 200);
    }

    public function getIndividualStudentDetails($schoolId, $studentId)
    {

        $tenant = Tenant::findOrFail($schoolId);
        tenancy()->initialize($tenant);
        $student = Student::findOrFail($studentId);


        if (!$tenant) {
            return response()->json([
                "status" => false,
                "message" => "There is no school with given id",
            ], 404);
        }


        if (!$student) {
            return response()->json([
                "status" => false,
                "message" => "Failed to found the student with given id",

            ], 404);
        }

        return response()->json([
            "status" => true,
            "message" => "Student Details Fetched",
            "student" => $student

        ], 200);

    }

    public function getIndividualStudentResult(Request $request, $schoolId, $studentId)
    {
        $tenant = Tenant::findOrFail($schoolId);
        tenancy()->initialize($tenant);

        $student = Student::with('class:id,name')->findOrFail($studentId);
        
        // Initialize service
        $calculationService = new \App\Services\ResultCalculationService();
        
        $academicYearId = $request->query('academic_year_id'); // Filter by academic year

        $query = Result::with([
            'subject:id,name,theory_marks,practical_marks,theory_pass_marks',
            'class:id,name',
            'teacher:id,name', // Fetch teacher details
            'teacher.classTeacherOf:id,name,class_teacher_id',
            'academicYear:id,name'
        ])->where('student_id', $student->id);

        if ($academicYearId) {
            $query->where('academic_year_id', $academicYearId);
        }

        $results = $query->get()->groupBy(['academic_year_id', 'class_id', 'exam_type']);

        $formattedResults = [];
        $overallMetrics = [];

        foreach ($results as $aYearId => $classes) {
            $academicYear = \App\Models\Admin\AcademicYear::find($aYearId);
            $academicYearName = $academicYear ? $academicYear->name : 'Unknown Year';
            
            // Get ResultSetting for this year
            $resultSetting = \App\Models\Admin\ResultSetting::where('academic_year_id', $aYearId)->first();

            foreach ($classes as $classId => $exams) {
                $className = \App\Models\Admin\SchoolClass::find($classId)->name ?? 'Unknown Class';
                $key = "{$academicYearName}"; // Key by Academic Year mostly

                foreach ($exams as $examType => $subjects) {
                    $totalObtained = 0;
                    $totalMax = 0;

                    $formattedSubjects = $subjects->map(function ($result) use (&$totalObtained, &$totalMax, $calculationService, $resultSetting) {
                        
                        $termId = $result->term_id;
                        $includePractical = ($termId && $resultSetting) ? $calculationService->shouldIncludePractical($termId, $resultSetting) : true;

                        $theoryFull = (float) ($result->subject->theory_marks ?? 0);
                        $practicalFull = $includePractical ? (float) ($result->subject->practical_marks ?? 0) : 0;

                        $theoryObtained = (float) $result->marks_theory;
                        $practicalObtained = $includePractical ? (float) $result->marks_practical : 0;

                        $totalObtained += ($theoryObtained + $practicalObtained);
                        $totalMax += ($theoryFull + $practicalFull);

                        return [
                            'subject_name' => $result->subject->name ?? 'N/A',
                            'marks_theory' => $theoryObtained,
                            'max_theory' => $theoryFull,
                            'marks_practical' => $practicalObtained,
                            'max_practical' => $practicalFull,
                            'total_obtained' => $theoryObtained + $practicalObtained,
                            'total_max' => $theoryFull + $practicalFull,
                            'gpa' => $result->gpa,
                            'exam_type' => $result->exam_type,
                            'teacher_name' => $result->teacher->name ?? 'N/A',
                            'teacher_assigned_class' => $result->teacher && $result->teacher->classTeacherOf ? $result->teacher->classTeacherOf->name : null,
                            'class_name' => $result->class->name ?? 'N/A',
                            'remarks' => $result->remarks,
                        ];
                    });

                    $percentage = $totalMax > 0 ? round(($totalObtained / $totalMax) * 100, 2) : 0;
                    $gpa = $subjects->avg('gpa');

                    // If user requested specific structure, we can adapt. default:
                    if (!isset($formattedResults[$key])) {
                        $formattedResults[$key] = [];
                    }

                    $formattedResults[$key][] = [
                        'exam_type' => $examType,
                        'class_name' => $className,
                        'subjects' => $formattedSubjects,
                        'metrics' => [
                            'total_obtained' => $totalObtained,
                            'max_marks' => $totalMax,
                            'percentage' => $percentage,
                            'gpa' => number_format($gpa, 2),
                            'grade' => $calculationService->getGradeFromPercentage($percentage),
                            'division' => $calculationService->getDivisionFromPercentage($percentage),
                        ]
                    ];
                }
            }
        }

        return response()->json([
            "status" => true,
            "message" => "Result fetched Success",
            "data" => [
                'student' => $student,
                'results' => $formattedResults
            ]
        ]);
    }
}
