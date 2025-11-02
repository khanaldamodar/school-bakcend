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

        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'dob' => 'nullable|date',
            'gender' => 'nullable|in:male,female,other',
            'email' => 'nullable|email|unique:students,email',
            'blood_group' => 'nullable|string',
            'is_disabled' => 'nullable|boolean',
            'is_tribe' => 'nullable|boolean',
            'phone' => 'nullable|string|max:20',
            'address' => 'string',
            'image' => 'nullable|file|image|max:2048',
            'class_id' => 'required|exists:classes,id',
            'roll_number' => 'nullable|string|max:50',
            'enrollment_year' => 'nullable|digits:4',
            'is_transferred' => 'nullable|boolean',
            'transferred_to' => 'nullable|string|max:255',
            'parents' => 'required|array|min:1',
            'parents.*.first_name' => 'required|string|max:255',
            'parents.*.last_name' => 'nullable|string|max:255',
            'parents.*.email' => 'required|email',
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
            foreach ($validated['parents'] as $parentData) {

                $parentUser = User::firstOrCreate(
                    ['email' => $parentData['email']],
                    [
                        'name' => $parentData['first_name'] . ' ' . ($parentData['last_name'] ?? ''),
                        'email' => $parentData['email'],
                        'phone' => $parentData['phone'] ?? null,
                        'password' => bcrypt($parentData['phone']),// stored only in users table
                        'role' => 'parent',
                    ]
                );


                $parent = ParentModel::firstOrCreate(
                    ['email' => $parentData['email']], // check if parent already exists by email
                    [
                        'first_name' => $parentData['first_name'],
                        'last_name' => $parentData['last_name'] ?? null,
                        'phone' => $parentData['phone'] ?? null,
                        'relation' => $parentData['relation'],
                        'user_id' => $parentUser->id
                    ]
                );



                // Link parent to student (avoid duplicates)
                $student->parents()->syncWithoutDetaching([$parent->id]);
            }

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Student and parent(s) created successfully',
                'data' => $student->load('parents')
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();
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

        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'email' => 'nullable|email|unique:students,email,' . $student->id,
            'phone' => 'nullable|string|max:20',
            'class_id' => 'required|exists:classes,id',
            'roll_number' => 'nullable|string|max:50',

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

            return response()->json([
                'status' => true,
                'message' => 'Student updated successfully',
                'data' => $student->load('parents')
            ], 200);

        } catch (\Throwable $e) {
            DB::rollBack();
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
        $students = $request->input('students', []);

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
                return response()->json([
                    'status' => false,
                    'message' => 'All student imports failed.',
                    'errors' => $failed,
                ], 422);
            }

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => "$successCount student(s) imported successfully.",
                'failed_rows' => $failed,
            ], 200);

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => 'Something went wrong during bulk upload.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


}
