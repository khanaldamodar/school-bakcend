<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Admin\Student;
use App\Models\Admin\Teacher;
use App\Models\Admin\Result;
use App\Models\Admin\ResultSetting;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AnalyticalReportController extends Controller
{
    public function index(Request $request, $domain)
    {
        $year = $request->query('year', Carbon::now()->year);
        $classId = $request->query('class_id');
        $isTribe = $request->query('is_tribe');
        $isDisabled = $request->query('is_disabled');

        $currentStats = $this->calculateStats($year, $classId, $isTribe, $isDisabled);
        $previousStats = $this->calculateStats($year - 1, $classId, $isTribe, $isDisabled);

        return response()->json([
            'status' => true,
            'message' => 'Analytical report fetched successfully',
            'data' => [
                'current_year' => $year,
                'stats' => $currentStats,
                'previous_year' => $year - 1,
                'previous_stats' => $previousStats,
            ]
        ], 200);
    }

    private function calculateStats($year, $classId, $isTribe, $isDisabled)
    {
        // 1. Calculate Students count
        $studentQuery = Student::query();

        // Check if we are looking at a past year or current year
        // We assume "current year" implies utilizing the 'class_id' on the student model directly.
        // For past years, we must rely on history.
        // However, since we don't have a reliable "School Current Year" setting, we default to current calendar year.
        // If the requested year is NOT the current calendar year, we try to use history.
        $isCurrentYear = ($year == Carbon::now()->year);

        if (!$isCurrentYear) {
            // Use history for class and year filtering
            $studentQuery->whereHas('histories', function ($q) use ($year, $classId) {
                $q->where('year', $year);
                if ($classId) {
                    $q->where('class_id', $classId);
                }
            });
        } else {
            // Use current student table
            if ($classId) {
                $studentQuery->where('class_id', $classId);
            }
            // If year is current, we also assume enrollment_year <= year, but that's usually true.
            // We can also filter by enrollment_year if needed, but 'class_id' implies active students.
        }

        // Apply filters common to both (attribute filters)
        if ($isTribe !== null) {
            $studentQuery->where('is_tribe', $isTribe);
        }
        if ($isDisabled !== null) {
            $studentQuery->where('is_disabled', $isDisabled);
        }

        $totalStudents = $studentQuery->count();
        $studentIds = $studentQuery->pluck('id');

        // 2. Calculate Teachers count
        // Teacher count is less likely to change based on Student filters like tribe/disabled
        // But if filtered by class, we should return teachers for that class.
        // If year is filtered, we take current teachers (no history).
        $teacherQuery = Teacher::query();
        if ($classId) {
            $teacherQuery->where(function ($q) use ($classId) {
                // Class Teacher
                $q->where('class_teacher_of_id', $classId)
                    // Or Subject Teacher for that class
                  ->orWhereHas('subjects', function ($sq) use ($classId) {
                      // Assuming pivot table 'class_subject_teacher' relates teachers to classes via subjects
                      // But 'subjects' relation on Teacher is usually belongsToMany Subject. 
                      // There is a 'class_subject_teacher' table (seen in ResultController).
                      // We need to check if the teacher is assigned to this class.
                      // Since Teacher model was not fully shown with all relations, 
                      // we'll rely on a raw check or existing relation if available.
                      // Let's use the raw check seen in ResultController:
                      // DB::table('class_subject_teacher')->where('teacher_id', $teacher->id)->...
                  });
            });
            
            // To do this optimally with Eloquent:
            // Fetch teacher IDs from pivot
            $teachersInClass = DB::table('class_subject_teacher')
                ->where('class_id', $classId)
                ->pluck('teacher_id')
                ->unique();
            
            $teacherQuery->where(function($q) use ($teachersInClass, $classId) {
                $q->whereIn('id', $teachersInClass)
                  ->orWhere('class_teacher_of_id', $classId);
            });
        }
        $totalTeachers = $teacherQuery->count();

        // 3. Calculate Pass Ratio
        if ($totalStudents == 0) {
            return [
                'total_students' => 0,
                'total_teachers' => $totalTeachers,
                'pass_ratio' => 0,
                'passed_students' => 0
            ];
        }

        // Get Pass Criteria
        // We need result settings.
        $resultSetting = ResultSetting::first(); 
        $passPercentage = 40; // Default
        $passGPA = 1.6; // Default
        
        // Logic to determine pass/fail
        // Query results for these students in this year
        // 'Result' table has 'final_result'
        
        // Filter results by student IDs
        $resultsQuery = Result::whereIn('student_id', $studentIds)
                              // Filter by Year (exam_date or created_at)
                              // Result table has 'exam_date'. 
                              ->whereYear('exam_date', $year);

        // If classId is set, filter results by classId as well (redundant if studentIds are already filtered, but safer)
        if ($classId) {
            $resultsQuery->where('class_id', $classId);
        }

        // We only care about the Final Result for the year.
        // Result table stores result per subject/term.
        // BUT `updateFinalResult` stores a 'final_result' value in the columns.
        // We select distinct student_id where final_result represents a Pass.
        
        // Problem: 'final_result' is stored on every row.
        // We need 1 status per student.
        // Let's take the MAX final_result for each student (assuming all rows have same final_result if updated correctly).
        
        $passedCount = 0;

        // Better: Fetch unique student_ids with their final_result
        // Since `updateFinalResult` updates ALL rows for that student/class/year, we can just grab one row per student.
        $studentResults = $resultsQuery->select('student_id', 'final_result')
                                       ->distinct()
                                       ->get();
                                       
        // If result_type is not available in Result directly, we rely on ResultSetting
        // Note: Result table does not store 'result_type'.
        
        foreach ($studentResults->unique('student_id') as $res) {
            $val = $res->final_result;
            if ($val === null) continue; // No final result calculated yet

            $passed = false;
            if ($resultSetting && $resultSetting->result_type == 'gpa') {
                if ($val >= $passGPA) $passed = true;
            } else {
                // Default to percentage check
                if ($val >= $passPercentage) $passed = true;
            }

            if ($passed) $passedCount++;
        }

        $passRatio = ($passedCount / $totalStudents) * 100;

        return [
            'total_students' => $totalStudents,
            'total_teachers' => $totalTeachers,
            'pass_ratio' => round($passRatio, 2),
            'passed_students' => $passedCount
        ];
    }
}
