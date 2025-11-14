<?php

namespace App\Http\Controllers\Government;

use App\Http\Controllers\Controller;
use App\Models\Admin\Student;
use App\Models\Tenant;
use Illuminate\Http\Request;

class IndividualSchoolStudents extends Controller
{
    public function getAllStudents($schoolId)
    {
        $tenant = Tenant::findOrFail($schoolId);
        tenancy()->initialize($tenant);
        $students = Student::all();


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
}
