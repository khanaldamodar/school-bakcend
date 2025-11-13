<?php

namespace App\Http\Controllers\Government;

use App\Http\Controllers\Controller;
use App\Models\Admin\Teacher;
use App\Models\Tenant;
use Illuminate\Http\Request;

class IndividualSchoolTeachers extends Controller
{

    //  public function showSchool($id){
    //     $tenant = Tenant::findOrFail($id);

    //     tenancy()->initialize($tenant);
    //     $students = Student::all();
    //     $teachers = Teacher::all();

    //     tenancy()->end();
    //     return  response()->json([
    //         "status"=> true,
    //         'teachers'=> $teachers,
    //         'students'=> $students,
    //         "message"=> "School Fetched SuccessFully"
    //     ],200);

    // }



    public function getAllTeachers($id)
    {
        $tenant = Tenant::findOrFail($id);
        tenancy()->initialize($tenant);

        if (!$tenant) {
            return response()->json([
                "status" => false,
                "message" => "There is no school with given id",
            ], 404);
        }

        $teachers = Teacher::all();

        if ($teachers->isEmpty()) {
            return response()->json([
                "status" => false,
                "message" => "Failed to found the teachers",

            ], 404);
        }

        return response()->json([
            "status" => true,
            "message" => "Student Fetched Successfully",
            "data" => $teachers,

        ], 200);
    }
}
