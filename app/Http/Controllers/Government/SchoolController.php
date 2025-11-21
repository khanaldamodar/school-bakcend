<?php

namespace App\Http\Controllers\Government;

use App\Http\Controllers\Controller;
use App\Models\Admin\Setting;
use App\Models\Admin\Student;
use App\Models\Admin\Teacher;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SchoolController extends Controller
{
    public function getSchoolsByLocalUnit(Request $request, $localUnit)
{
    $schools = Tenant::where('local_unit', $localUnit)->get();

    $data = [];

    foreach ($schools as $school) {

        // 🔄 Switch database of the tenant (very important)
        tenancy()->initialize($school);

        // Count students and teachers for this school
        $studentCount = Student::count();
        $teacherCount = Teacher::count();

        // Append result
        $data[] = [
            "school" => $school,
            "counts" => [
                "studentCount" => $studentCount,
                "teacherCount" => $teacherCount
            ]
        ];
    }

    return response()->json([
        'status' => true,
        'message' => "Schools fetched successfully",
        'total_schools' => $schools->count(),
        'data' => $data
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


    public function showSchool($id)
    {
        $tenant = Tenant::findOrFail($id);

        tenancy()->initialize($tenant);
        // tenancy()->initialize($tenant);

        // dd([
        //     'default' => DB::getDefaultConnection(),
        //     'tenant_name' => DB::connection('tenant')->getDatabaseName(),
        // ]);

        $students = Student::count();
        $teachers = Teacher::count();
        $scl = Setting::find(1);
        tenancy()->end();
        return response()->json([
            "status" => true,
            'teachers' => $teachers,
            'students' => $students,
            'details' => [
                "name" => $scl->name ?? null,
                "logo" => $scl->logo ?? null,
                "about" => $scl->about ?? null,
                "address" => $scl->address ?? null,
                "phone" => $scl->phone ?? null,
                "email" => $scl->email ?? null,
                "established_date" => $scl->established_date ?? null,
                "principle" => $scl->principle ?? null,



            ],
            "message" => "School Fetched SuccessFully"
        ], 200);

    }
}
