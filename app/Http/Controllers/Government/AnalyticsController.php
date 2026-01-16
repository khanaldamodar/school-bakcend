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

    public function ethnicity(Request $request)
    {
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
                foreach ($ethnicityStats as $stat) {
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

    public function comprehensive(Request $request)
    {
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
                    $studentsList = Student::select('first_name', 'last_name', 'gender', 'roll_number', 'class_id')->limit(1000)->get()->map(function ($s) {
                        return [
                            'name' => $s->first_name . ' ' . $s->last_name,
                            'gender' => $s->gender,
                            'roll' => $s->roll_number,
                            'class' => $s->class_id
                        ];
                    });

                    $teachersList = Teacher::select('name', 'gender', 'email', 'phone')->limit(200)->get()->map(function ($t) {
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


        if ($request->is_tribe === 1) {

            $totalTribeStudents = Student::where("is_tribe", $request->is_tribe)->count();
            $tribeMaleStudents = Student::where("is_tribe", $request->is_tribe)->where("gender", "male")->count();
            $tribeFemaleStudents = Student::where("is_tribe", $request->is_tribe)->where("gender", "female")->count();
            $tribeOthersStudents = Student::where("is_tribe", $request->is_tribe)->where("gender", "others")->count();

            $tribeRecords[] = [
                "total" => $totalTribeStudents,
                "male" => $tribeMaleStudents,
                "female" => $tribeFemaleStudents,
                "others" => $tribeOthersStudents
            ];

            return response()->json([
                "status" => true,
                "message" => "Tribe student fetched",
                "tribe" => $tribeRecords
            ]);
        }

        $records[] = [
            "school_id" => $request->schoolId,
            "total" => $totalStudents,
            "male" => $maleStudents,
            "female" => $femaleStudents,
            "others" => $otherStudents
        ];

        return response()->json([
            "status" => true,
            "message" => "Single school Report",
            "records" => $records
        ]);
    }
    public function getClassActivityReport(Request $request)
    {
        $request->validate([
            "schools" => "required|array|min:1",
            "class_id" => "nullable", // Can be array or integer
            "academic_year_id" => "nullable|integer",
            "gender" => "nullable", // Can be array or string
            "is_disabled" => "nullable|string",
            "ethnicity" => "nullable", // Can be array or string
            "age_group" => "nullable" // Can be array or string
        ]);

        $cacheKey = 'gov_class_activity_report_v3_' . md5(json_encode($request->all()));

        return Cache::remember($cacheKey, 60, function () use ($request) {
            $results = [];

            foreach ($request->schools as $schoolDb) {
                try {
                    $tenant = Tenant::findOrFail($schoolDb);
                    tenancy()->initialize($tenant);
                } catch (\Exception $e) {
                    continue;
                }

                // Get class IDs from request (could be array or single value)
                $requestClassIds = $request->class_id;
                if ($requestClassIds && !is_array($requestClassIds)) {
                    $requestClassIds = [$requestClassIds];
                }

                // Determine Academic Year (Use request or default to current)
                $academicYearId = $request->academic_year_id;
                $academicYearDetails = null;

                if ($academicYearId) {
                    $ay = \App\Models\Admin\AcademicYear::find($academicYearId);
                    if ($ay) {
                        $academicYearDetails = ['id' => $ay->id, 'name' => $ay->name, 'is_current' => $ay->is_current];
                    }
                } else {
                    $ay = \App\Models\Admin\AcademicYear::current();
                    if ($ay) {
                        $academicYearId = $ay->id;
                        $academicYearDetails = ['id' => $ay->id, 'name' => $ay->name, 'is_current' => true];
                    }
                }

                $studentQuery = Student::query();

                // Filter by Academic Year using StudentClassHistory
                // Since students table doesn't have academic_year_id, we join with history
                if ($academicYearId) {
                    $studentQuery->join('student_class_histories', 'students.id', '=', 'student_class_histories.student_id')
                        ->where('student_class_histories.academic_year_id', $academicYearId)
                        ->select('students.*'); // Avoid column collisions
                } else {
                    // Fallback: If no history for that year, maybe just show current students?
                    // But logic says we must filter by year if we found one.
                    // For safety if for some reason join fails or logic is desired differently:
                    // The previous code assumed column existed. 
                }

                // Apply filters if provided
                if (!empty($requestClassIds)) {
                    $studentQuery->whereIn('students.class_id', $requestClassIds);
                }

                // Gender filter (accepts array or string)
                if ($request->filled('gender')) {
                    if (is_array($request->gender)) {
                        $studentQuery->whereIn('students.gender', $request->gender);
                    } else {
                        $studentQuery->where('students.gender', $request->gender);
                    }
                }

                // Disability filter
                if ($request->filled('is_disabled')) {
                    $studentQuery->where('students.is_disabled', $request->is_disabled);
                }

                // Ethnicity filter (accepts array or string)
                if ($request->filled('ethnicity')) {
                    if (is_array($request->ethnicity)) {
                        $studentQuery->whereIn('students.ethnicity', $request->ethnicity);
                    } else {
                        $studentQuery->where('students.ethnicity', $request->ethnicity);
                    }
                }

                // Age group filter
                if ($request->filled('age_group')) {
                    $now = \Carbon\Carbon::now();

                    if (is_array($request->age_group)) {
                        $studentQuery->where(function ($query) use ($request, $now) {
                            foreach ($request->age_group as $ageGroup) {
                                if ($ageGroup === '0-5') {
                                    $query->orWhere('students.dob', '>=', $now->copy()->subYears(5));
                                } elseif ($ageGroup === '5-10') {
                                    $query->orWhereBetween('students.dob', [
                                        $now->copy()->subYears(10),
                                        $now->copy()->subYears(6)->subDay()
                                    ]);
                                } elseif ($ageGroup === '10-15') {
                                    $query->orWhereBetween('students.dob', [
                                        $now->copy()->subYears(15),
                                        $now->copy()->subYears(11)->subDay()
                                    ]);
                                } elseif ($ageGroup === '15-20') {
                                    $query->orWhereBetween('students.dob', [
                                        $now->copy()->subYears(20),
                                        $now->copy()->subYears(16)->subDay()
                                    ]);
                                } elseif ($ageGroup === '20-25') {
                                    $query->orWhereBetween('students.dob', [
                                        $now->copy()->subYears(25),
                                        $now->copy()->subYears(21)->subDay()
                                    ]);
                                } elseif ($ageGroup === 'above-25') {
                                    $query->orWhere('students.dob', '<', $now->copy()->subYears(25));
                                }
                            }
                        });
                    } else {
                        $ageGroup = $request->age_group;

                        if ($ageGroup === '0-5') {
                            $studentQuery->where('students.dob', '>=', $now->copy()->subYears(5));
                        } elseif ($ageGroup === '5-10') {
                            $studentQuery->whereBetween('students.dob', [
                                $now->copy()->subYears(10),
                                $now->copy()->subYears(6)->subDay()
                            ]);
                        } elseif ($ageGroup === '10-15') {
                            $studentQuery->whereBetween('students.dob', [
                                $now->copy()->subYears(15),
                                $now->copy()->subYears(11)->subDay()
                            ]);
                        } elseif ($ageGroup === '15-20') {
                            $studentQuery->whereBetween('students.dob', [
                                $now->copy()->subYears(20),
                                $now->copy()->subYears(16)->subDay()
                            ]);
                        } elseif ($ageGroup === '20-25') {
                            $studentQuery->whereBetween('students.dob', [
                                $now->copy()->subYears(25),
                                $now->copy()->subYears(21)->subDay()
                            ]);
                        } elseif ($ageGroup === 'above-25') {
                            $studentQuery->where('students.dob', '<', $now->copy()->subYears(25));
                        }
                    }
                }

                // Get the DISTINCT classes from the filtered students
                $filteredClasses = [];
                if (!empty($requestClassIds)) {
                    // If class filter is applied, get only those classes
                    $filteredClasses = \DB::table('classes')
                        ->select('id as class_id', 'name as class_name')
                        ->whereIn('id', $requestClassIds)
                        ->orderBy('id')
                        ->get()
                        ->map(function ($class) {
                            return [
                                'class_id' => (int) $class->class_id,
                                'class_name' => $class->class_name
                            ];
                        })
                        ->toArray();
                } else {
                    // If no class filter, get classes from filtered students
                    $distinctClassIds = (clone $studentQuery)
                        ->distinct()
                        ->pluck('students.class_id')
                        ->filter() // Remove null values
                        ->toArray();

                    if (!empty($distinctClassIds)) {
                        $filteredClasses = \DB::table('classes')
                            ->select('id as class_id', 'name as class_name')
                            ->whereIn('id', $distinctClassIds)
                            ->orderBy('id')
                            ->get()
                            ->map(function ($class) {
                                return [
                                    'class_id' => (int) $class->class_id,
                                    'class_name' => $class->class_name
                                ];
                            })
                            ->toArray();
                    }
                }

                // Calculate age groups from DOB of filtered students
                $ageGroups = [
                    '0-5' => 0,
                    '5-10' => 0,
                    '10-15' => 0,
                    '15-20' => 0,
                    '20-25' => 0,
                    'above-25' => 0
                ];

                $students = $studentQuery->get(['students.dob as dob']);
                foreach ($students as $student) {
                    if ($student->dob) {
                        try {
                            $dob = \Carbon\Carbon::parse($student->dob);
                            $age = $dob->age;

                            if ($age <= 5) {
                                $ageGroups['0-5']++;
                            } elseif ($age <= 10) {
                                $ageGroups['5-10']++;
                            } elseif ($age <= 15) {
                                $ageGroups['10-15']++;
                            } elseif ($age <= 20) {
                                $ageGroups['15-20']++;
                            } elseif ($age <= 25) {
                                $ageGroups['20-25']++;
                            } else {
                                $ageGroups['above-25']++;
                            }
                        } catch (\Exception $e) {
                            // Skip invalid dates
                        }
                    }
                }

                // Get ethnicity stats from filtered students
                // To avoid ONLY_FULL_GROUP_BY issues with the joined query,
                // we first get the filtered student IDs, then aggregate separately
                $filteredStudentIds = (clone $studentQuery)->pluck('students.id')->toArray();
                
                if (!empty($filteredStudentIds)) {
                    $ethnicityStats = Student::whereIn('id', $filteredStudentIds)
                        ->selectRaw('COALESCE(ethnicity, "Unknown") as ethnicity, count(*) as count')
                        ->groupBy('ethnicity')
                        ->orderByDesc('count')
                        ->get()
                        ->map(function ($item) {
                            return [
                                'ethnicity' => $item->ethnicity ?: 'Unknown',
                                'count' => (int) $item->count
                            ];
                        })
                        ->toArray();
                } else {
                    $ethnicityStats = [];
                }

                // Get academic results for filtered students
                // Use the determined academicYearId for filtering results
                $academicQuery = FinalResult::whereNull('subject_id');

                if ($academicYearId) {
                    $academicQuery->where('academic_year_id', $academicYearId);
                }

                // Apply class filter to academic results
                if (!empty($requestClassIds)) {
                    $academicQuery->whereIn('class_id', $requestClassIds);
                } elseif (isset($distinctClassIds) && !empty($distinctClassIds)) {
                    // If students filtered by other criteria, only include their classes
                    $academicQuery->whereIn('class_id', $distinctClassIds);
                }

                // Get student IDs from our filtered query to ensure academic results match
                $filteredStudentIds = $studentQuery->pluck('students.id')->toArray();
                if (!empty($filteredStudentIds)) {
                    $academicQuery->whereIn('student_id', $filteredStudentIds);
                }

                $passCount = (clone $academicQuery)->where('is_passed', true)->count();
                $failCount = (clone $academicQuery)->where('is_passed', false)->count();

                // Calculate total students from filtered query
                $totalFilteredStudents = $studentQuery->count('students.id');

                $results[] = [
                    "school_id" => $schoolDb,
                    "school_name" => $tenant->name,
                    "academic_year" => $academicYearDetails, // Return details of year used
                    "classes" => $filteredClasses,
                    "age_groups" => $ageGroups,
                    "ethnicity" => array_values($ethnicityStats),
                    "academic" => [
                        "passed" => $passCount,
                        "failed" => $failCount,
                        "total" => $passCount + $failCount
                    ],
                    "total_students" => $totalFilteredStudents
                ];
            }

            return response()->json([
                "status" => true,
                "message" => "Class activity report generated successfully",
                "filters_applied" => [
                    "schools" => $request->schools,
                    "class_ids" => $requestClassIds ?? [],
                    "gender" => $request->gender,
                    "ethnicity" => $request->ethnicity,
                    "age_groups" => $request->age_group,
                    "is_disabled" => $request->is_disabled,
                    "academic_year_id" => $request->academic_year_id
                ],
                "data" => $results
            ]);
        });
    }

    public function getTeacherAnalyticsReport(Request $request)
    {
        $request->validate([
            "schools" => "required|array|min:1",
            "schools.*" => "string",

            // accept multiple
            "gender" => "nullable|array",
            "gender.*" => "string",

            "post" => "nullable|array",
            "post.*" => "string",

            "level" => "nullable|array",
            "level.*" => "string",

            "ethnicity" => "nullable|array",
            "ethnicity.*" => "string",

            "age_group" => "nullable|array",
            "age_group.*" => "string", // optionally restrict to allowed values via Rule::in(...)
        ]);

        // Normalize (also supports legacy single string payloads)
        $toArray = function ($value): array {
            if ($value === null)
                return [];
            if (is_array($value))
                return array_values(array_filter($value, fn($v) => $v !== null && $v !== ""));
            return [$value];
        };

        $payload = $request->all();

        // Make cache key stable (arrays order won't change the key)
        foreach (["schools", "gender", "post", "level", "ethnicity", "age_group"] as $k) {
            if (isset($payload[$k]) && is_array($payload[$k])) {
                $tmp = $payload[$k];
                sort($tmp);
                $payload[$k] = $tmp;
            }
        }

        $cacheKey = 'gov_teacher_analytics_report_' . md5(json_encode($payload));

        return Cache::remember($cacheKey, 600, function () use ($request, $toArray) {
            $results = [];

            $genders = $toArray($request->input("gender"));
            $posts = $toArray($request->input("post"));
            $levels = $toArray($request->input("level"));
            $ethnicities = $toArray($request->input("ethnicity"));
            $ageGroups = $toArray($request->input("age_group"));

            foreach ($request->schools as $schoolDb) {
                try {
                    $tenant = Tenant::findOrFail($schoolDb);
                    tenancy()->initialize($tenant);
                } catch (\Exception $e) {
                    continue;
                }

                $teacherQuery = Teacher::query();

                // Multi filters
                if (!empty($genders)) {
                    $teacherQuery->whereIn('gender', $genders);
                }

                if (!empty($posts)) {
                    $teacherQuery->whereIn('post', $posts);
                }

                if (!empty($levels)) {
                    // Your frontend calls it "level" but DB column is "grade"
                    $teacherQuery->whereIn('grade', $levels);
                }

                if (!empty($ethnicities)) {
                    $teacherQuery->whereIn('ethnicity', $ethnicities);
                }

                // Multiple age groups => OR ranges on dob
                if (!empty($ageGroups)) {
                    $now = \Carbon\Carbon::now();

                    $teacherQuery->whereNotNull('dob')->where(function ($q) use ($ageGroups, $now) {
                        foreach ($ageGroups as $ageGroup) {
                            if ($ageGroup === '20-30') {
                                $q->orWhereBetween('dob', [$now->copy()->subYears(30), $now->copy()->subYears(20)]);
                            } elseif ($ageGroup === '30-40') {
                                $q->orWhereBetween('dob', [$now->copy()->subYears(40), $now->copy()->subYears(31)]);
                            } elseif ($ageGroup === '40-50') {
                                $q->orWhereBetween('dob', [$now->copy()->subYears(50), $now->copy()->subYears(41)]);
                            } elseif ($ageGroup === '50-60') {
                                $q->orWhereBetween('dob', [$now->copy()->subYears(60), $now->copy()->subYears(51)]);
                            } elseif ($ageGroup === 'above-60') {
                                $q->orWhere('dob', '<', $now->copy()->subYears(61));
                            }
                        }
                    });
                }

                // 1) Age Groups distribution (within the filtered teacherQuery)
                $ageGroupsCount = [
                    '20-30' => 0,
                    '30-40' => 0,
                    '40-50' => 0,
                    '50-60' => 0,
                    'above-60' => 0
                ];

                $teachersDob = (clone $teacherQuery)->whereNotNull('dob')->pluck('dob');
                foreach ($teachersDob as $dob) {
                    try {
                        $age = \Carbon\Carbon::parse($dob)->age;
                        if ($age >= 20 && $age <= 30)
                            $ageGroupsCount['20-30']++;
                        elseif ($age > 30 && $age <= 40)
                            $ageGroupsCount['30-40']++;
                        elseif ($age > 40 && $age <= 50)
                            $ageGroupsCount['40-50']++;
                        elseif ($age > 50 && $age <= 60)
                            $ageGroupsCount['50-60']++;
                        elseif ($age > 60)
                            $ageGroupsCount['above-60']++;
                    } catch (\Exception $e) {
                    }
                }

                // 2) Ethnicity Stats
                $ethnicityStats = (clone $teacherQuery)
                    ->selectRaw('ethnicity, count(*) as count')
                    ->whereNotNull('ethnicity')
                    ->where('ethnicity', '!=', '')
                    ->groupBy('ethnicity')
                    ->orderByDesc('count')
                    ->get();

                // 3) Post Stats
                $postStats = (clone $teacherQuery)
                    ->selectRaw('post, count(*) as count')
                    ->whereNotNull('post')
                    ->where('post', '!=', '')
                    ->groupBy('post')
                    ->get();

                // 4) Level Stats
                $levelStats = (clone $teacherQuery)
                    ->selectRaw('grade as level, count(*) as count')
                    ->whereNotNull('grade')
                    ->where('grade', '!=', '')
                    ->groupBy('grade')
                    ->get();

                $results[] = [
                    "school_id" => $schoolDb,
                    "school_name" => $tenant->name,
                    "age_groups" => $ageGroupsCount,
                    "ethnicity" => $ethnicityStats,
                    "posts" => $postStats,
                    "levels" => $levelStats,
                    "total_teachers" => $teacherQuery->count()
                ];

                // optional but often good practice with multi-tenancy:
                // tenancy()->end();
            }

            return response()->json([
                "status" => true,
                "data" => $results
            ]);
        });
    }
}
