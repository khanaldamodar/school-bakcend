<?php

namespace App\Http\Controllers\Government;

use App\Http\Controllers\Controller;
use App\Models\Admin\Teacher;
use App\Models\Tenant;
use Illuminate\Http\Request;

class AllTeachersController extends Controller
{
    // public function getAllTeachers($localUnit){
    // $tenant = Tenant::where('local_unit', $localUnit)->get();
    // tenancy()->initialize($tenant);
    // response()->json([
    //     "data"=> $tenant
    // ]);
    // }



    public function getAllTeachers(Request $request, $localUnit)
{
    $schools = Tenant::where('local_unit', $localUnit)->get();

    $data = [];

    foreach ($schools as $school) {

        
        tenancy()->initialize($school);

        // Fetch teachers
        $teachers = Teacher::all();

        // Count teachers
        $teacherCount = $teachers->count();

        // Append result for this school
        $data[] = [
            "school" => $school,
            "counts" => [
                "teacherCount" => $teacherCount
            ],
            "teachers" => $teachers  
        ];
    }

    return response()->json([
        'status' => true,
        'message' => "Schools fetched successfully",
        'total_schools' => $schools->count(),
        'data' => $data
    ], 200);
}

}
