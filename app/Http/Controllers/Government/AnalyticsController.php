<?php

namespace App\Http\Controllers\Government;

use App\Http\Controllers\Controller;
use App\Models\Admin\Student;
use App\Models\Admin\Teacher;
use App\Models\Admin\FinalResult;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;

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
            "is_tribe" => "nullable", // Kept for backward compat in validation but ignored in logic if needed
            "blood_group" => "nullable",
            "is_disabled" => "nullable",
            "qualification" => "nullable",
            "ethnicity" => "nullable"
        ]);

        // Generate Cache Key based on request parameters
        $cacheKey = 'gov_analytics_filter_' . md5(json_encode($request->all()));
        
        // Return cached result if available (Cache for 10 minutes)
        return Cache::remember($cacheKey, 600, function () use ($request) {
            $results = [];

            foreach ($request->schools as $schoolDb) {

                try {
                    $tenant = Tenant::findOrFail($schoolDb);
                    tenancy()->initialize($tenant); 
                } catch (\Exception $e) {
                    continue;
                }

                $query = $request->type === "students"
                    ? Student::query()
                    : Teacher::query();

                // Apply filters dynamically
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

                 if ($request->filled("ethnicity")) {
                    $query->where("ethnicity", $request->ethnicity);
                }

                // Optimization: aggregation instead of get() -> count()
                $stats = $query->selectRaw('
                    count(*) as total,
                    sum(case when gender = "male" then 1 else 0 end) as male,
                    sum(case when gender = "female" then 1 else 0 end) as female,
                    sum(case when gender = "other" or gender = "others" then 1 else 0 end) as other,
                    sum(case when is_disabled = 1 then 1 else 0 end) as disabled
                ')->first();

                // Tribe removed as per user request (use ethnicity instead)
                
                $results[] = [
                    "school_id" => $schoolDb,
                    "total" => $stats->total,
                    "male" => $stats->male,
                    "female" => $stats->female,
                    "other" => $stats->other,
                    "tribe" => 0, // Zeroed out as we are moving to ethnicity
                    "disabled" => $stats->disabled
                ];
            }

            return response()->json([
                "mode" => $request->mode,
                "type" => $request->type,
                "result" => $results
            ]);
        });
    }

    public function ethnicity(Request $request) {
        $request->validate([
            "schools" => "required|array|min:1",
            "type" => "required|in:students,teachers",
        ]);

        $cacheKey = 'gov_analytics_ethnicity_' . md5(json_encode($request->all()));

        return Cache::remember($cacheKey, 600, function () use ($request) {
            $results = [];

            foreach ($request->schools as $schoolDb) {
                try {
                    $tenant = Tenant::findOrFail($schoolDb);
                    tenancy()->initialize($tenant);
                } catch (\Exception $e) {
                    continue;
                }

                $query = $request->type === "students" ? Student::query() : Teacher::query();

                // Group by ethnicity
                $ethnicityStats = $query->selectRaw('ethnicity, count(*) as count')
                    ->whereNotNull('ethnicity')
                    ->groupBy('ethnicity')
                    ->get();
                
                $formattedStats = [];
                foreach($ethnicityStats as $stat) {
                    if (!empty($stat->ethnicity)) {
                        $formattedStats[$stat->ethnicity] = $stat->count;
                    }
                }

                $results[] = [
                    "school_id" => $schoolDb,
                    "stats" => $formattedStats
                ];
            }
            
            return response()->json([
                "status" => true,
                "data" => $results
            ]);
        });
    }

    public function comprehensive(Request $request) {
        $request->validate([
            "schools" => "required|array|min:1",
            "type" => "required|in:students,teachers",
        ]);

        $cacheKey = 'gov_analytics_comprehensive_' . md5(json_encode($request->all()));

        return Cache::remember($cacheKey, 600, function () use ($request) {
            $results = [];

            foreach ($request->schools as $schoolDb) {
                try {
                    $tenant = Tenant::findOrFail($schoolDb);
                    tenancy()->initialize($tenant);
                } catch (\Exception $e) {
                    continue;
                }

                // 1. General Stats (Gender, Disabled) for STUDENTS
                // We use conditional aggregation for students
                $studentQuery = Student::query();
                $stats = $studentQuery->selectRaw('
                    count(*) as total,
                    sum(case when gender = "male" then 1 else 0 end) as male,
                    sum(case when gender = "female" then 1 else 0 end) as female,
                    sum(case when gender = "other" or gender = "others" then 1 else 0 end) as other,
                    sum(case when is_disabled = 1 then 1 else 0 end) as disabled
                ')->first();

                // 2. Student Ethnicity Stats
                $studentEthnicity = Student::query()
                    ->selectRaw('ethnicity, count(*) as count')
                    ->whereNotNull('ethnicity')
                    ->groupBy('ethnicity')
                    ->get()
                    ->mapWithKeys(function ($item) {
                        return [$item->ethnicity => $item->count];
                    });

                // 3. Teacher Stats (Gender, Ethnicity)
                $teacherQuery = Teacher::query();
                $teacherStats = $teacherQuery->selectRaw('
                    count(*) as total,
                    sum(case when gender = "male" then 1 else 0 end) as male,
                    sum(case when gender = "female" then 1 else 0 end) as female,
                    sum(case when gender = "other" or gender = "others" then 1 else 0 end) as other
                ')->first();

                $teacherEthnicity = Teacher::query()
                    ->selectRaw('ethnicity, count(*) as count')
                    ->whereNotNull('ethnicity')
                    ->groupBy('ethnicity')
                    ->get()
                    ->mapWithKeys(function ($item) {
                        return [$item->ethnicity => $item->count];
                    });

                // 4. Optional: Detailed Lists (Names)
                $studentsList = [];
                $teachersList = [];

                if ($request->has('include_names') && $request->include_names) {
                    $studentsList = Student::select('first_name', 'last_name', 'gender', 'roll_number', 'class_id')->limit(1000)->get()->map(function($s) {
                        return [
                            'name' => $s->first_name . ' ' . $s->last_name,
                            'gender' => $s->gender,
                            'roll' => $s->roll_number,
                            'class' => $s->class_id
                        ];
                    });
                     
                    $teachersList = Teacher::select('name', 'gender', 'email', 'phone')->limit(200)->get()->map(function($t) {
                        return [
                            'name' => $t->name,
                            'gender' => $t->gender,
                            'email' => $t->email,
                            'phone' => $t->phone
                        ];
                    });
                }

                $results[] = [
                    "school_id" => $schoolDb,
                    "general" => [ // Student General
                        "total" => $stats->total,
                        "male" => $stats->male,
                        "female" => $stats->female,
                        "other" => $stats->other,
                        "disabled" => $stats->disabled,
                        "tribe" => 0
                    ],
                    "ethnicity" => $studentEthnicity,
                    "academic" => [
                        "passed" => FinalResult::whereNull('subject_id')->where('is_passed', true)->count(),
                        "failed" => FinalResult::whereNull('subject_id')->where('is_passed', false)->count(),
                        "average_gpa" => FinalResult::whereNull('subject_id')->avg('final_gpa') ?? 0,
                    ],
                    "teacher_stats" => [
                        "total" => $teacherStats->total,
                        "male" => $teacherStats->male,
                        "female" => $teacherStats->female,
                        "other" => $teacherStats->other,
                        "ethnicity" => $teacherEthnicity
                    ],
                    "details" => [
                        "students" => $studentsList,
                        "teachers" => $teachersList
                    ]
                ];
            }
            
            return response()->json([
                "status" => true,
                "data" => $results
            ]);
        });
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
