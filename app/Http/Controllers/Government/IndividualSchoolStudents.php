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

    public function getIndividualStudentResult($schoolId, $studentId)
    {
        // $validator = Validator::make([$schoolId, $studentId], [
        //     "schoolId" => "required|string",
        //     "studentId" => "string|required"
        // ]);

        // if ($validator->fails()) {
        //     return response()->json([
        //         "status" => false,
        //         "message" => "validation error",
        //         "error" => $validator->errors()

        //     ], 422);
        // }

        $tenant = Tenant::findOrFail($schoolId);
        tenancy()->initialize($tenant);

        $student = Student::with('class:id,name')->findOrFail($studentId);

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

        // dd($results);

        return response()->json([
            "status" => true,
            "message" => "Result fetched Success",
            "result" => $results
        ]);



    }
}
