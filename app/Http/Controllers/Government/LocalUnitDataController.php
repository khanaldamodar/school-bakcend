<?php

namespace App\Http\Controllers\Government;

use App\Http\Controllers\Controller;
use App\Models\Admin\Student;
use App\Models\Admin\Teacher;
use App\Models\Tenant;
use Illuminate\Http\Request;

class LocalUnitDataController extends Controller
{
    /**
     * Fetch all teachers and students for all schools in a local unit.
     */
    public function getAllTeachersAndStudents($localUnit)
    {
        $schools = Tenant::where('local_unit', $localUnit)->get();

        $data = [];

        foreach ($schools as $school) {
            
            // Switch tenant database
            tenancy()->initialize($school);

            // Fetch teachers with class assignment info
            $teachers = Teacher::with('classTeacherOf')->get();
            
            // Fetch students with their class info
            $students = Student::with('class')->get();

            // Append result for this school
            $data[] = [
                "school" => $school,
                "teacher_count" => $teachers->count(),
                "student_count" => $students->count(),
                "teachers" => $teachers,
                "students" => $students
            ];
        }

        return response()->json([
            'status' => true,
            'message' => "All teachers and students fetched successfully for local unit: " . $localUnit,
            'total_schools' => $schools->count(),
            'data' => $data
        ], 200);
    }
}
