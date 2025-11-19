<?php

namespace App\Http\Controllers\Government;

use App\Http\Controllers\Controller;
use App\Models\Admin\Student;
use App\Models\Admin\Teacher;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
class AnalyticsController extends Controller
{
    // ? To filter the students
    public function singleSchool(
        $schoolId,
        $isTribe = null,
        $isDisable = null,
        $gender = null,
    ) {
        $tenant = Tenant::findOrFail($schoolId);
        tenancy()->initialize($tenant);
        $query = Student::query();
        if ($isTribe !== null) {
            $query->where("is_tribe", $isTribe);
        }
        if ($isDisable !== null) {
            $query->where("is_disabled", $isDisable);
        }
        if ($gender !== null) {
            $query->where("gender", $gender);
        }
        $students = $query->get();

        return response()->json($students);
    }

    public function filterStudents(Request $request)
    {
        // Initialize Tenant
        $tenant = Tenant::findOrFail($request->schoolId);
        tenancy()->initialize($tenant);

        // Start query
        $query = Student::query();

        // Apply filters only if value exists
        if ($request->filled('first_name')) {
            $query->where('first_name', 'like', '%' . $request->first_name . '%');
        }

        if ($request->filled('last_name')) {
            $query->where('last_name', 'like', '%' . $request->last_name . '%');
        }

        if ($request->filled('gender')) {
            $query->where('gender', $request->gender);
        }

        if ($request->filled('class_id')) {
            $query->where('class_id', $request->class_id);
        }

        if ($request->filled('is_disabled')) {
            $query->where('is_disabled', $request->is_disabled);
        }

        if ($request->filled('is_tribe')) {
            $query->where('is_tribe', $request->is_tribe);
        }

        if ($request->filled('blood_group')) {
            $query->where('blood_group', $request->blood_group);
        }

        if ($request->filled('enrollment_year')) {
            $query->where('enrollment_year', $request->enrollment_year);
        }

        if ($request->filled('roll_number')) {
            $query->where('roll_number', $request->roll_number);
        }

        if ($request->filled('phone')) {
            $query->where('phone', $request->phone);
        }

        if ($request->filled('is_transferred')) {
            $query->where('is_transferred', $request->is_transferred);
        }

        // Execute result
        $students = $query->get();

        return response()->json([
            "count" => $students->count(),
            "data" => $students
        ]);
    }
    public function filterTeachers(Request $request)
    {
        // Ensure tenant
        $tenant = Tenant::findOrFail($request->schoolId);
        tenancy()->initialize($tenant);

        // Start teacher query
        $query = Teacher::query();

        // Apply filters if provided

        if ($request->filled('name')) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }

        if ($request->filled('email')) {
            $query->where('email', $request->email);
        }

        if ($request->filled('phone')) {
            $query->where('phone', $request->phone);
        }

        if ($request->filled('gender')) {
            $query->where('gender', $request->gender);
        }

        if ($request->filled('dob')) {
            $query->where('dob', $request->dob);
        }

        if ($request->filled('nationality')) {
            $query->where('nationality', $request->nationality);
        }

        if ($request->filled('class_teacher_of')) {
            $query->where('class_teacher_of', $request->class_teacher_of);
        }

        if ($request->filled('qualification')) {
            $query->where('qualification', 'like', '%' . $request->qualification . '%');
        }

        if ($request->filled('address')) {
            $query->where('address', 'like', '%' . $request->address . '%');
        }

        if ($request->filled('blood_group')) {
            // handle O+ → O%2B issue
            $bloodGroup = str_replace(' ', '+', $request->blood_group);
            $query->where('blood_group', $bloodGroup);
        }

        if ($request->filled('is_disabled')) {
            $query->where('is_disabled', $request->is_disabled);
        }

        if ($request->filled('is_tribe')) {
            $query->where('is_tribe', $request->is_tribe);
        }

        // Execute
        $teachers = $query->get();

        return response()->json([
            "count" => $teachers->count(),
            "data" => $teachers
        ]);
    }

    public function multipleSchool($school1Id, $school2Id, )
    {

        // First School
        $tenant1 = Tenant::findOrFail($school1Id);
        $tenant2 = Tenant::findOrFail($school2Id);

        // Initialize the db
        tenancy()->initialize($tenant1);
        tenancy()->initialize($tenant2);

        $school1Std = Student::count();
        $school2Std = Student::count();
        dd($school1Std, $school2Std);
    }
    public function filter(Request $request)
{
    $request->validate([
        "mode" => "required|in:single,multiple",
        "schools" => "required|array|min:1",
        "type" => "required|in:students,teachers",

        "gender" => "nullable",
        "is_tribe" => "nullable",
        "blood_group" => "nullable",
        "is_disabled" => "nullable",
        "qualification" => "nullable"
    ]);

    $results = [];

    foreach ($request->schools as $schoolDb) {

        $tenant = Tenant::findOrFail($schoolDb);
        tenancy()->initialize($tenant);  // Switch tenant DB

        // Choose which model to use
        $query = $request->type === "students"
            ? Student::query()
            : Teacher::query();

        // Apply filters dynamically
        if ($request->filled("is_tribe")) {
            $query->where("is_tribe", $request->is_tribe);
        }

        if ($request->filled("gender")) {
            $query->where("gender", $request->gender);
        }

        if ($request->filled("blood_group") && $request->type === "students") {
            $bg = str_replace(" ", "+", $request->blood_group);
            $query->where("blood_group", $bg);
        }

        if ($request->filled("qualification") && $request->type === "teachers") {
            $query->where("qualification", "like", "%" . $request->qualification . "%");
        }

        if ($request->filled("is_disabled")) {
            $query->where("is_disabled", $request->is_disabled);
        }

        // Get filtered dataset
        $data = $query->get();

        // Gender-based count (from final filtered data)
        $maleCount = $data->where("gender", "male")->count();
        $femaleCount = $data->where("gender", "female")->count();
        $otherCount = $data->where("gender", "others")->count();

        // Push final response
        $results[] = [
            "school_id" => $schoolDb,
            "total" => $data->count(),
            "male" => $maleCount,
            "female" => $femaleCount,
            "others" => $otherCount,
        ];
    }

    return response()->json([
        "mode" => $request->mode,
        "type" => $request->type,
        "result" => $results
    ]);
}


    public function singleSchoolStudentFilter(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'schoolId' => "required|string",
            "is_tribe" => "nullable",
            
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "message" => "Validation Failed",
                "error" => $validator->errors()
            ], 400);
        }


        $tenant = Tenant::findOrFail($request->schoolId);
        tenancy()->initialize($tenant); 

        $totalStudents = Student::all()->count();
        $maleStudents = Student::where("gender", "male")->count();
        $femaleStudents = Student::where("gender", "female")->count();
        $otherStudents = Student::where("gender", "others")->count();
        
        
        if($request->is_tribe === 1){

            $totalTribeStudents = Student::where("is_tribe", $request->is_tribe)->count();
            $tribeMaleStudents = Student::where("is_tribe", $request->is_tribe)->where("gender", "male")->count();
            $tribeFemaleStudents = Student::where("is_tribe", $request->is_tribe)->where("gender", "female")->count();
            $tribeOthersStudents = Student::where("is_tribe", $request->is_tribe)->where("gender", "others")->count();

            $tribeRecords[] =[
                "total" => $totalTribeStudents,
                "male"=> $tribeMaleStudents,
                "female"=> $tribeFemaleStudents,
                "others"=> $tribeOthersStudents
            ];

        return response()->json([
            "status"=> true,
            "message"=> "Tribe student fetched",
            "tribe"=> $tribeRecords
        ]);
        }
        
        $records[] = [
            "school_id"=> $request->schoolId,
            "total"=> $totalStudents,
            "male"=> $maleStudents,
            "female"=> $femaleStudents,
            "others" => $otherStudents
        ];

        return response()->json([
            "status"=> true,
            "message"=> "Single school Report",
            "records"=> $records
        ]);

        

    }
}
