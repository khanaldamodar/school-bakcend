<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Admin\ParentModel;
use App\Models\Admin\Teacher;
use App\Models\Admin\Student;
use App\Models\Admin\SchoolClass;
use App\Models\Admin\Subject;
use App\Models\Admin\AcademicYear;
use App\Models\Admin\FinalResult;

class DashboardController extends Controller
{
    /**
     * Get statistics for admin dashboard.
     */
    public function stats(Request $request, $domain)
    {
        $totalTeachers = Teacher::count();
        $totalStudents = Student::count();
        $totalClasses = SchoolClass::count();  // optional
        // $totalSubjects = Subject::count();    // optional
        $totalParents = ParentModel::count();
        
        // Pass Statistics for the whole school (Current Academic Year)
        $currentYear = AcademicYear::where('is_current', true)->first();
        $passedCount = 0;
        $passPercentage = 0;

        if ($currentYear) {
            $overallResults = FinalResult::where('academic_year_id', $currentYear->id)
                ->whereNull('subject_id');
            
            $totalResults = $overallResults->count();
            $passedCount = $overallResults->where('is_passed', true)->count();
            
            if ($totalResults > 0) {
                $passPercentage = round(($passedCount / $totalResults) * 100, 2);
            }
        }

  

        return response()->json([
            'status' => true,
            'data' => [
                'total_teachers' => $totalTeachers,
                'total_students' => $totalStudents,
                'total_classes' => $totalClasses,
                'total_parents' => $totalParents,
                'passed_students_count' => $passedCount,
                'pass_percentage' => $passPercentage
            ]
        ]);

    }
}
