<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\ImageUploadHelper;
use App\Http\Controllers\Controller;
use App\Models\Admin\ParentModel;
use App\Models\Admin\Student;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use App\Services\TenantLogger;

class StudentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Student::with(['class', 'parents']);

        if ($request->has('class') && $request->class != '') {
            $query->where('class_id', $request->class);
        }

        if ($request->has('search') && $request->search != '') {
            $query->where(function ($q) use ($request) {
                $q->where('first_name', 'like', '%' . $request->search . '%')
                    ->orWhere('last_name', 'like', '%' . $request->search . '%');
            });
        }

        $students = $query->paginate($request->get('per_page', 10));

        return response()->json([
            'status' => true,
            'message' => 'Students fetched successfully',
            'data' => $students->items(),
            'meta' => [
                'current_page' => $students->currentPage(),
                'last_page' => $students->lastPage(),
                'per_page' => $students->perPage(),
                'total' => $students->total(),
            ],
        ]);
    }




    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {

        // Get the database name of the system
        $tenantDomain = tenant()->database;
        TenantLogger::studentInfo('Creating student', ['tenant' => $tenantDomain, 'request_ip' => $request->ip()]);

        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'dob' => 'nullable|date',
            'gender' => 'nullable|in:male,female,other',
            'email' => 'nullable|email',
            'blood_group' => 'nullable|string',
            'is_disabled' => 'nullable|boolean',
            'ethnicity'=>'string|nullable',
            'is_tribe' => 'nullable|boolean',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'image' => 'nullable|file|image|max:2048',
            'class_id' => 'required|exists:classes,id',
            'roll_number' => 'nullable|string|max:50',
            'enrollment_year' => 'nullable|digits:4',
            'is_transferred' => 'nullable|boolean',
            'transferred_to' => 'nullable|string|max:255',
            'parents' => 'required|array|min:1',
            'parents.*.first_name' => 'required|string|max:255',
            'parents.*.last_name' => 'nullable|string|max:255',
            'parents.*.email' => 'nullable|email',
            'parents.*.phone' => 'nullable|string|max:20',
            'parents.*.relation' => 'required|in:father,mother,guardian',
        ]);


        DB::beginTransaction();

        try {

            $imageData = null;
            $cloudinaryId = null;
            if ($request->hasFile('image')) {
                $imageData = ImageUploadHelper::uploadToCloud(
                    $request->file('image'),
                    "{$tenantDomain}/students"
                );

                if ($imageData) {
                    $cloudinaryId = $imageData['public_id']; // save this in DB
                }
            }



            // Create student
            $student = Student::create([
                'first_name' => $validated['first_name'],
                'middle_name' => $validated['middle_name'] ?? null,
                'last_name' => $validated['last_name'] ?? null,
                'dob' => $validated['dob'] ?? null,
                'gender' => $validated['gender'] ?? null,
                'email' => $validated['email'] ?? null,
                'blood_group' => $validated['blood_group'] ?? null,
                'is_disabled' => $validated['is_disabled'] ?? 0,
                'is_tribe' => $validated['is_tribe'] ?? 0,
                'phone' => $validated['phone'] ?? null,
                'class_id' => $validated['class_id'],
                'roll_number' => $validated['roll_number'] ?? null,
                'enrollment_year' => $validated['enrollment_year'] ?? null,
                'is_transferred' => $validated['is_transferred'] ?? false,
                'transferred_to' => $validated['transferred_to'] ?? null,
                'address' => $validated['address'] ?? null,
                'image' => $imageData['url'] ?? null,
                'cloudinary_id' => $cloudinaryId,

            ]);


            // Create student user account
            $studentUser = User::firstOrCreate(
                ['email' => $validated['email']],
                [
                    'name' => $validated['first_name'] . ' ' . ($validated['last_name'] ?? ''),
                    'email' => $validated['email'],
                    'phone' => $validated['phone'] ?? null,
                    'password' => bcrypt($validated['phone']),
                    'role' => 'student',
                ]
            );

            $student->user_id = $studentUser->id;
            $student->save();

            // Attach Parents
            // Optimization: Fetch existing users and parents in one go
            $parentEmails = collect($validated['parents'])->pluck('email')->unique();
            $existingUsers = User::whereIn('email', $parentEmails)->get()->keyBy('email');
            $existingParents = ParentModel::whereIn('email', $parentEmails)->get()->keyBy('email');
            
            $parentIds = [];

            foreach ($validated['parents'] as $parentData) {

                // 1. Handle User Account
                if ($existingUsers->has($parentData['email'])) {
                    $parentUser = $existingUsers[$parentData['email']];
                } else {
                    $parentUser = User::create([
                        'name' => $parentData['first_name'] . ' ' . ($parentData['last_name'] ?? ''),
                        'email' => $parentData['email'],
                        'phone' => $parentData['phone'] ?? null,
                        'password' => bcrypt($parentData['phone']), // This is still slow but unavoidable if we need new users
                        'role' => 'parent',
                    ]);
                    // Add to local cache
                    $existingUsers->put($parentUser->email, $parentUser);
                }

                // 2. Handle Parent Model
                if ($existingParents->has($parentData['email'])) {
                    $parent = $existingParents[$parentData['email']];
                } else {
                    $parent = ParentModel::create([
                        'email' => $parentData['email'],
                        'first_name' => $parentData['first_name'],
                        'last_name' => $parentData['last_name'] ?? null,
                        'phone' => $parentData['phone'] ?? null,
                        'relation' => $parentData['relation'],
                        'user_id' => $parentUser->id
                    ]);
                    $existingParents->put($parent->email, $parent);
                }

                $parentIds[] = $parent->id;
            }

            // Sync updated parents
            $student->parents()->syncWithoutDetaching($parentIds);

            DB::commit();

            TenantLogger::studentInfo('Student created successfully', ['student_id' => $student->id, 'tenant' => $tenantDomain]);
            return response()->json([
                'status' => true,
                'message' => 'Student and parent(s) created successfully',
                'data' => $student->load('parents')
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Student creation failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString(), 'tenant' => $tenantDomain]);
            TenantLogger::studentError('Student creation failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString(), 'tenant' => $tenantDomain]);
            return response()->json([
                'status' => false,
                'message' => 'Something went wrong',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($domain, string $id)
    {
        $students = Student::with(['class', 'parents'])->findOrFail($id);

        return response()->json([
            'status' => true,
            'message' => 'Students fetched successfully',
            'data' => $students
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $domain, string $id)
    {
        $student = Student::findOrFail($id);
        TenantLogger::studentInfo('Updating student', ['student_id' => $id, 'tenant' => $domain]);

        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'email' => 'nullable|email|unique:students,email,' . $student->id,
            'phone' => 'nullable|string|max:20',
            'class_id' => 'required|exists:classes,id',
            'roll_number' => 'nullable|string|max:50',
            'ethnicity' => 'nullable|string',

            // parents array validation
            'parents' => 'nullable|array',
            'parents.*.first_name' => 'required_with:parents|string|max:255',
            'parents.*.last_name' => 'nullable|string|max:255',
            'parents.*.email' => 'required_with:parents|email',
            'parents.*.phone' => 'nullable|string|max:20',
            'parents.*.password' => 'nullable|min:6',
            'parents.*.relation' => 'required_with:parents|in:father,mother,guardian',
        ]);

        DB::beginTransaction();

        try {
            // Update student
            $student->update([
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'] ?? null,
                'email' => $validated['email'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'class_id' => $validated['class_id'],
                'roll_number' => $validated['roll_number'] ?? null,
            ]);

            // If parents data provided, handle update
            if (!empty($validated['parents'])) {
                $parentIds = [];

                foreach ($validated['parents'] as $parentData) {
                    $parent = ParentModel::updateOrCreate(
                        ['email' => $parentData['email']], // find parent by email
                        [
                            'first_name' => $parentData['first_name'],
                            'last_name' => $parentData['last_name'] ?? null,
                            'phone' => $parentData['phone'] ?? null,
                            'relation' => $parentData['relation'],
                            // Only update password if provided
                            'password' => isset($parentData['password'])
                                ? bcrypt($parentData['password'])
                                : DB::raw('password'),
                        ]
                    );

                    $parentIds[] = $parent->id;
                }

                // Sync updated parents (remove old ones if not provided in request)
                $student->parents()->sync($parentIds);
            }

            DB::commit();

            TenantLogger::studentInfo('Student updated successfully', ['student_id' => $id]);
            return response()->json([
                'status' => true,
                'message' => 'Student updated successfully',
                'data' => $student->load('parents')
            ], 200);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Student update failed', ['student_id' => $id, 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            TenantLogger::studentError('Student update failed', ['student_id' => $id, 'error' => $e->getMessage()]);
            return response()->json([
                'status' => false,
                'message' => 'Update failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy($domain, string $id)
    {
        $student = Student::findOrFail($id);
        $student->delete();

        return response()->json([
            'status' => true,
            'message' => "Student Deleted Successfully",
        ]);

    }

    public function filterByClass(Request $request, $domain, $classId)
    {

        $students = Student::with(['class', 'parents'])
            ->where('class_id', $classId)
            ->get();

        return response()->json([
            'status' => true,
            'message' => 'Students fetched successfully for class',
            'data' => $students
        ]);
    }

    public function filterByClassAndName(Request $request, $domain, $classId)
    {
        $search = $request->query('name'); // search string from query params

        $students = Student::with(['class', 'parents'])
            ->where('class_id', $classId)
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%");
                });
            })
            ->get();

        return response()->json([
            'status' => true,
            'message' => 'Students filtered successfully',
            'data' => $students
        ]);
    }


    public function profile(Request $request)
    {
        // $request->user() comes from Sanctum auth
        $userId = $request->user()->id;
 
        // Fetch the student linked to this user_id
        $student = Student::with(['class', 'parents'])
            ->where('user_id', $userId)
            ->first();

        if (!$student) {
            return response()->json([
                'status' => false,
                'message' => 'Student not found'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Student profile fetched successfully',
            'data' => $student
        ]);
    }




    public function bulkUpload(Request $request)
    {
        TenantLogger::studentInfo('Starting student bulk upload', ['request_ip' => $request->ip()]);
        $students = [];

        if ($request->hasFile('file')) {
            $request->validate([
                'file' => 'required|mimes:xlsx,csv',
            ]);

            try {
                $file = $request->file('file');
                $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file->getPathname());
                $sheet = $spreadsheet->getActiveSheet();
                $rows = $sheet->toArray(); // Get all rows

                if (count($rows) <= 1) {
                    return response()->json([
                        'status' => false,
                        'message' => 'File is empty or contains only headers.'
                    ], 400);
                }

                $header = array_map('trim', array_shift($rows)); // Extract headers and trim spaces
                // Helper to find index case-insensitively
                $getIndex = function($name) use ($header) {
                    $key = array_search(strtolower($name), array_map('strtolower', $header));
                    return $key === false ? null : $key;
                };

                // Required Columns Mapping
                $map = [
                    'first_name' => $getIndex('first_name'),
                    'middle_name' => $getIndex('middle_name'),
                    'last_name' => $getIndex('last_name'),
                    'email' => $getIndex('email'),
                    'phone' => $getIndex('phone'),
                    'class_id' => $getIndex('class_id'),
                    'roll_number' => $getIndex('roll_number'),
                    'ethnicity' => $getIndex('ethnicity'),
                    // Parent info
                    'parent_first_name' => $getIndex('parent_first_name'),
                    'parent_last_name' => $getIndex('parent_last_name'),
                    'parent_email' => $getIndex('parent_email'),
                    'parent_phone' => $getIndex('parent_phone'),
                    'parent_relation' => $getIndex('parent_relation'),
                ];

                foreach ($rows as $row) {
                    // Skip if primary required fields missing or row empty
                    if (empty($row) || ($map['first_name'] !== null && empty($row[$map['first_name']]))) continue;

                    $studentData = [
                        'first_name' => $map['first_name'] !== null ? $row[$map['first_name']] : null,
                        'middle_name' => $map['middle_name'] !== null ? $row[$map['middle_name']] : null,
                        'last_name' => $map['last_name'] !== null ? $row[$map['last_name']] : null,
                        'email' => $map['email'] !== null ? $row[$map['email']] : null,
                        'phone' => $map['phone'] !== null ? $row[$map['phone']] : null,
                        'class_id' => $map['class_id'] !== null ? $row[$map['class_id']] : null,
                        'roll_number' => $map['roll_number'] !== null ? $row[$map['roll_number']] : null,
                        'ethnicity' => $map['ethnicity'] !== null ? $row[$map['ethnicity']] : null,
                        
                        'parents' => []
                    ];

                    if ($map['parent_email'] !== null && !empty($row[$map['parent_email']])) {
                        $studentData['parents'][] = [
                            'first_name' => $map['parent_first_name'] !== null ? $row[$map['parent_first_name']] : null,
                            'last_name' => $map['parent_last_name'] !== null ? $row[$map['parent_last_name']] : null,
                            'email' => $row[$map['parent_email']],
                            'phone' => $map['parent_phone'] !== null ? $row[$map['parent_phone']] : null,
                            'relation' => $map['parent_relation'] !== null ? $row[$map['parent_relation']] : 'guardian',
                        ];
                    }

                    $students[] = $studentData;
                }

            } catch (\Exception $e) {
                TenantLogger::studentError('Bulk upload file parsing error', ['error' => $e->getMessage()]);
                 return response()->json([
                    'status' => false,
                    'message' => 'Error parsing file: ' . $e->getMessage()
                ], 400);
            }

        } else {
            $students = $request->input('students', []);
        }

        if (empty($students)) {
            return response()->json([
                'status' => false,
                'message' => 'No student data provided.'
            ], 400);
        }

        $successCount = 0;
        $failed = [];

        DB::beginTransaction();

        try {
            foreach ($students as $index => $studentData) {

                if (isset($studentData['parents']) && is_string($studentData['parents'])) {
                    $decoded = json_decode($studentData['parents'], true);
                    $studentData['parents'] = is_array($decoded) ? $decoded : [];
                }
                // ✅ Validate each student row
                $validator = Validator::make($studentData, [
                    'first_name' => 'required|string|max:255',
                    'middle_name' => 'nullable|string|max:255',
                    'last_name' => 'nullable|string|max:255',
                    'email' => 'nullable|email|unique:students,email',
                    'phone' => 'nullable|string|max:20',
                    'class_id' => 'required|exists:classes,id',
                    'roll_number' => 'nullable|string|max:50',
                    'ethnicity' => 'nullable|string|max:50',
                    

                    // parents array validation
                    'parents' => 'required|array|min:1',
                    'parents.*.first_name' => 'required|string|max:255',
                    'parents.*.last_name' => 'nullable|string|max:255',
                    'parents.*.email' => 'required|email',
                    'parents.*.phone' => 'nullable|string|max:20',
                    'parents.*.relation' => 'required|in:father,mother,guardian',
                ]);

                if ($validator->fails()) {
                    $failed[] = [
                        'row' => $index + 1,
                        'errors' => $validator->errors()->all(),
                    ];
                    continue; // skip this row
                }

                $validated = $validator->validated();

                try {
                    // Create student
                    $student = Student::create([
                        'first_name' => $validated['first_name'],
                        'middle_name' => $validated['middle_name'] ?? null,
                        'last_name' => $validated['last_name'] ?? null,
                        'email' => $validated['email'] ?? null,
                        'phone' => $validated['phone'] ?? null,
                        'class_id' => $validated['class_id'],
                        'roll_number' => $validated['roll_number'] ?? null,
                        'ethnicity' => $validated['ethnicity'] ?? null,
                    ]);

                    // Create student user account
                    $studentUser = User::firstOrCreate(
                        ['email' => $validated['email']],
                        [
                            'name' => $validated['first_name'] . ' ' . ($validated['last_name'] ?? ''),
                            'email' => $validated['email'],
                            'phone' => $validated['phone'] ?? null,
                            'password' => bcrypt($validated['phone']),
                            'role' => 'student',
                        ]
                    );

                    $student->user_id = $studentUser->id;
                    $student->save();

                    // Attach Parents
                    foreach ($validated['parents'] as $parentData) {
                        $parentUser = User::firstOrCreate(
                            ['email' => $parentData['email']],
                            [
                                'name' => $parentData['first_name'] . ' ' . ($parentData['last_name'] ?? ''),
                                'email' => $parentData['email'],
                                'phone' => $parentData['phone'] ?? null,
                                'password' => bcrypt($parentData['phone']),
                                'role' => 'parent',
                            ]
                        );

                        $parent = ParentModel::firstOrCreate(
                            ['email' => $parentData['email']],
                            [
                                'first_name' => $parentData['first_name'],
                                'last_name' => $parentData['last_name'] ?? null,
                                'phone' => $parentData['phone'] ?? null,
                                'relation' => $parentData['relation'],
                                'user_id' => $parentUser->id,
                            ]
                        );

                        $student->parents()->syncWithoutDetaching([$parent->id]);
                    }

                    $successCount++;
                } catch (\Throwable $e) {
                    $failed[] = [
                        'row' => $index + 1,
                        'errors' => [$e->getMessage()],
                    ];
                }
            }

            if ($successCount === 0) {
                DB::rollBack();
                TenantLogger::studentWarning('Bulk upload failed completely', ['errors' => $failed]);
                return response()->json([
                    'status' => false,
                    'message' => 'All student imports failed.',
                    'errors' => $failed,
                ], 422);
            }

            DB::commit();
            TenantLogger::studentInfo('Bulk upload completed', ['success_count' => $successCount, 'failed_count' => count($failed)]);

            return response()->json([
                'status' => true,
                'message' => "$successCount student(s) imported successfully.",
                'failed_rows' => $failed,
            ], 200);

        } catch (\Throwable $e) {
            DB::rollBack();
            TenantLogger::studentError('Bulk upload fatal error', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json([
                'status' => false,
                'message' => 'Something went wrong during bulk upload.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


}
