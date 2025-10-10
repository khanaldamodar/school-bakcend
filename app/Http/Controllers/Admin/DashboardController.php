<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\ParentModel;
use App\Models\Admin\Teacher;
use App\Models\Admin\Student;
use App\Models\Admin\SchoolClass; // if you have a Class model
use App\Models\Admin\Subject; // if you have a Subject model

class DashboardController extends Controller
{
    /**
     * Get statistics for admin dashboard.
     */
    public function stats()
    {
        $totalTeachers = Teacher::count();
        $totalStudents = Student::count();
        $totalClasses = SchoolClass::count();  // optional
        // $totalSubjects = Subject::count();    // optional
        $totalParents = ParentModel::count();

  

        return response()->json([
            'status' => true,
            'data' => [
                'total_teachers' => $totalTeachers,
                'total_students' => $totalStudents,
                'total_classes' => $totalClasses,
                'total_parents' => $totalParents
            ]
        ]);

    }
}
