<?php

namespace App\Http\Controllers\Government;

use App\Http\Controllers\Controller;
use App\Models\Admin\Student;
use App\Models\Admin\Teacher;
use App\Models\Tenant;
use Illuminate\Http\Request;

class SchoolController extends Controller
{
    public function getSchoolsByLocalUnit(Request $request, $localUnit)
    {
        $schools = Tenant::where('local_unit', $localUnit)->get();
        return response()->json([
            'status' => true,
            'data' => $schools,
            "message" => "Schools fetched successfully",
            'total_schools' => $schools->count()
        ], 200);
    }
    public function getSchoolsByLocalUnitWard(Request $request, $localUnit, $ward)
    {
        $schools = Tenant::where('local_unit', $localUnit)
            ->where('ward', $ward)
            ->get();
        return response()->json([
            'status' => true,
            'data' => $schools,
            "message" => "Schools fetched successfully",
            'total' => $schools->count()
        ], 200);
    }


    public function showSchool($id){
        $tenant = Tenant::findOrFail($id);

        tenancy()->initialize($tenant);
        $students = Student::all();
        $teachers = Teacher::all();
        
        tenancy()->end();
        return  response()->json([
            "status"=> true,
            'teachers'=> $teachers,
            'students'=> $students,
            "message"=> "School Fetched SuccessFully"
        ],200);

    }
}
