<?php
namespace App\Http\Controllers\Admin;

use App\Helpers\ImageUploadHelper;
use App\Http\Controllers\Controller;
use App\Models\Admin\SchoolClass;
use App\Models\Admin\Teacher;
use App\Models\Admin\ClassTeacherHistory;
use App\Models\Admin\SubjectTeacherHistory;
use App\Models\Admin\AcademicYear;
use App\Models\Admin\Subject;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Cloudinary\Cloudinary;
use Illuminate\Validation\Rule;
use App\Services\TenantLogger;

class TeacherController extends Controller
{

    public function index($domain)
    {
        $teachers = Teacher::with(['subjects:id,name', 'classTeacherOf:id,name,class_teacher_id,section', 'roles', 'user'])->get();

        return response()->json([
            'status' => true,
            'message' => 'Teachers fetched successfully',
            'data' => $teachers
        ]);
    }


    public function store(Request $request, $domain)
    {

        $tenantDomain = tenant()->database;
        // dd($tenantDomain);
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'required|string|unique:users,phone',
            'qualification' => 'nullable|string',
            'address' => 'nullable|string',
            'blood_group' => 'nullable|string',
            'is_disabled' => 'required|boolean',
            'disability_options' => 'required|in:none,visual,hearing,physical,mental,other',
            'is_tribe' => 'required|boolean',
            'image' => 'nullable|file|image|max:2048',
            'grade' => 'nullable',
            'gender' => 'required|string',
            'dob' => 'required|date',
            'nationality' => 'required|string',
            'subject_ids' => 'nullable|array',
            'subject_ids.*' => 'exists:subjects,id',
            'class_teacher_of' => [
                'nullable',
                'exists:classes,id',
                function ($attribute, $value, $fail) {
                    if ($value && SchoolClass::where('id', $value)->whereNotNull('class_teacher_id')->exists()) {
                        $fail('The selected class already has a class teacher.');
                    }
                }
            ],
            'ethnicity' => 'nullable|string',
            'post' => 'nullable|string',
            'dob_bs' => 'string|nullable',
            'joining_data_bs' => 'string|nullable',
            'joining_date' => 'nullable',
            'role_ids' => 'nullable|array',
            'role_ids.*' => 'exists:teacher_roles,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        DB::beginTransaction();

        try {
            $imageData = null;
            $cloudinaryId = null;
            if ($request->hasFile('image')) {
                $imageData = ImageUploadHelper::uploadToCloud(
                    $request->file('image'),
                    "{$tenantDomain}/teachers"
                );

                if ($imageData) {
                    $cloudinaryId = $imageData['public_id']; // save this in DB
                }
            }
            //  Create user
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'password' => Hash::make($data['phone']),
                'role' => 'teacher',
            ]);

            //  Create teacher profile
            $teacher = Teacher::create([
                'user_id' => $user->id,
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'qualification' => $data['qualification'] ?? null,
                'address' => $data['address'] ?? null,
                'blood_group' => $data['blood_group'] ?? null,
                'is_disabled' => $data['is_disabled'],
                'disability_options' => $data['disability_options'] ?? null,
                'is_tribe' => $data['is_tribe'],
                'image' => $imageData['url'] ?? null,
                'cloudinary_id' => $cloudinaryId,
                'gender' => $data['gender'],
                'grade' => $data['grade'] ?? null,
                'ethnicity' => $data['ethnicity'] ?? null,
                'post' => $data['post'],
                'dob' => $data['dob'] ?? null,
                'nationality' => $data['nationality'],
                'class_teacher_of' => $data['class_teacher_of'] ?? null,
                'dob_bs' => $data['dob_bs'] ?? null,
                'joining_data_bs' => $data['joining_data_bs'] ?? null,
                'joining_date' => $data['joining_date'] ?? null 
            ]);

            //  Sync subjects
            if (!empty($data['subject_ids'])) {
                $teacher->subjects()->sync($data['subject_ids']);
            }

            // Sync Roles
            if (!empty($data['role_ids'])) {
                $teacher->roles()->sync($data['role_ids']);
            }

            //  Assign class teacher
            if (!empty($data['class_teacher_of'])) {
                $class = SchoolClass::find($data['class_teacher_of']);
                $class->class_teacher_id = $teacher->id;
                $class->save();
            }

            DB::commit();

            TenantLogger::logCreate('teachers', "Teacher registered: {$teacher->name}", [
                'id' => $teacher->id,
                'email' => $teacher->email
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Teacher created successfully',
                'data' => $teacher->load('subjects', 'classTeacherOf', 'user', 'roles'),
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

    public function show($domain, $id)
    {
        $teacher = Teacher::with(['subjects:id,name', 'classTeacherOf:id,name,class_teacher_id,section', 'roles', 'user'])->findOrFail($id);
        // dd($teacher);
        return response()->json([
            'status' => true,
            'message' => 'Teacher fetched successfully',
            'data' => $teacher
        ]);
    }

    public function update(Request $request, $domain, $id)
    {
        $teacher = Teacher::findOrFail($id);
        $user = $teacher->user;

        $tenantDomain = tenant()->database;

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $user->id,
            'phone' => 'sometimes|string|unique:users,phone,' . $user->id,
            'qualification' => 'nullable|string',
            'address' => 'nullable|string',
            'blood_group' => 'nullable|string',
            'is_disabled' => 'sometimes|boolean',
            'disability_options' => 'sometimes|in:none,visual,hearing,physical,mental,other',
            'is_tribe' => 'sometimes|boolean',
            'image' => 'nullable|file|image|max:2048',
            'gender' => 'sometimes|string',
            'grade' => 'sometimes|string',
            'dob' => 'sometimes|date',
            'nationality' => 'sometimes|string',
            'subject_ids' => 'nullable|array',
            'subject_ids.*' => 'exists:subjects,id',
            'class_teacher_of' => [
                'nullable',
                'exists:classes,id',
                function ($attribute, $value, $fail) use ($id) {
                    if ($value) {
                        // Check if the class already has a different teacher
                        $class = SchoolClass::find($value);
                        if ($class && $class->class_teacher_id && $class->class_teacher_id != $id) {
                            $fail('The selected class already has another class teacher.');
                        }

                        // Check if this teacher is already assigned to a different class
                        $existingClass = SchoolClass::where('class_teacher_id', $id)
                            ->where('id', '!=', $value)
                            ->first();
                        if ($existingClass) {
                            $fail("This teacher is already assigned to {$existingClass->name}. Please unassign them from there first.");
                        }
                    }
                }
            ],
            'ethnicity' => 'sometimes|string',
            'post' => 'string|nullable',
            'dob_bs' => 'string|nullable',
            'joining_data_bs' => 'string|nullable',
            'joining_date' => 'nullable',
            'role_ids' => 'nullable|array',
            'role_ids.*' => 'exists:teacher_roles,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        DB::beginTransaction();

        try {
            $cloudinaryId = $teacher->cloudinary_id;
            $imageUrl = $teacher->image;

            //  Handle new image upload
            if ($request->hasFile('image')) {
                // Delete old image from Cloudinary
                if ($cloudinaryId) {
                    Storage::disk('cloudinary')->delete($cloudinaryId);
                }

                $imageData = ImageUploadHelper::uploadToCloud(
                    $request->file('image'),
                    "{$tenantDomain}/teachers"
                );

                if ($imageData) {
                    $imageUrl = $imageData['url'];
                    $cloudinaryId = $imageData['public_id'];
                }
            }

            // Update user
            $user->update([
                'name' => $data['name'] ?? $user->name,
                'email' => $data['email'] ?? $user->email,
                'phone' => $data['phone'] ?? $user->phone,
            ]);

            // Update teacher
            $teacher->update([
                'name' => $data['name'] ?? $teacher->name,
                'email' => $data['email'] ?? $teacher->email,
                'phone' => $data['phone'] ?? $teacher->phone,
                'qualification' => $data['qualification'] ?? $teacher->qualification,
                'address' => $data['address'] ?? $teacher->address,
                'blood_group' => $data['blood_group'] ?? $teacher->blood_group,
                'is_disabled' => $data['is_disabled'] ?? $teacher->is_disabled,
                'disability_options' => $data['disability_options'] ?? $teacher->disability_options,
                'is_tribe' => $data['is_tribe'] ?? $teacher->is_tribe,
                'image' => $imageUrl,
                'cloudinary_id' => $cloudinaryId,
                'gender' => $data['gender'] ?? $teacher->gender,
                'dob' => $data['dob'] ?? $teacher->dob,
                'nationality' => $data['nationality'] ?? $teacher->nationality,
                'class_teacher_of' => $data['class_teacher_of'] ?? $teacher->class_teacher_of,
                'grade' => $data['grade'] ?? $teacher->grade,
                'ethnicity' => $data['ethnicity'] ?? $teacher->ethnicity,
                'post' => $data['post'] ?? $teacher->post,
                'dob_bs' => $data['dob_bs'] ?? $teacher->dob_bs,
                'joining_data_bs' => $data['joining_data_bs'] ?? $teacher->joining_data_bs,
                'joining_date' => $data['joining_date'] ?? $teacher->joining_date
            ]);

            //  Sync subjects
            if ($request->has('subject_ids')) {
                $teacher->subjects()->sync($data['subject_ids'] ?? []);
            }

            // Sync Roles
            if ($request->has('role_ids')) {
                $teacher->roles()->sync($data['role_ids'] ?? []);
            }

            //  Update class teacher assignment
            if (!empty($data['class_teacher_of'])) {
                $class = SchoolClass::find($data['class_teacher_of']);
                $class->class_teacher_id = $teacher->id;
                $class->save();
            }

            DB::commit();

            TenantLogger::logUpdate('teachers', "Teacher updated: {$teacher->name}", [
                'id' => $teacher->id
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Teacher updated successfully',
                'data' => $teacher->load('subjects', 'classTeacherOf', 'user', 'roles'),
            ], 200);

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => 'Something went wrong',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function destroy($domain, $id)
    {
        $teacher = Teacher::findOrFail($id);

        // Set is_deleted to true for teacher
        $suffix = '_deleted_' . time();
        $teacher->update([
            'is_deleted' => true,
            'email' => $teacher->email ? $teacher->email . $suffix : null,
            'phone' => $teacher->phone ? $teacher->phone . $suffix : null,
        ]);

        // Also soft-delete the associated user if exists
        if ($teacher->user_id) {
            $user = User::find($teacher->user_id);
            if ($user) {
                $user->update([
                    'is_deleted' => true,
                    'email' => $user->email . $suffix,
                ]);
            }
        }

        TenantLogger::logDelete('teachers', "Teacher deleted: {$teacher->name}", [
            'id' => $id,
            'name' => $teacher->name
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Teacher deleted successfully'
        ]);
    }

    public function me(Request $request)
    {
        $user = $request->user();

        $teacher = Teacher::with(['classTeacherOf', 'classSubjects', 'roles'])->where('user_id', $user->id)->first();

        if (!$teacher) {
            return response()->json([
                'status' => true,
                'message' => 'Teacher profile not found'
            ], 404);
        }

        // Pre-fetch all classes for this teacher to avoid N+1 problem
        $classes = [];
        $classIds = $teacher->classSubjects->pluck('pivot.class_id')->unique();
        $allClasses = SchoolClass::whereIn('id', $classIds)->select('id', 'name', 'section')->get()->keyBy('id');

        foreach ($teacher->classSubjects as $subject) {
            $classId = $subject->pivot->class_id;

            if (!isset($classes[$classId])) {
                $cls = $allClasses->get($classId);
                if (!$cls) continue;

                $classes[$classId] = [
                    'id' => $cls->id,
                    'name' => $cls->name,
                    'section' => $cls->section,
                    'is_class_teacher' => ($teacher->classTeacherOf->id ?? null) == $cls->id,
                    'subjects' => [],
                ];
            }

            $classes[$classId]['subjects'][] = [
                'id' => $subject->id,
                'name' => $subject->name,
            ];
        }

        // Convert to indexed array
        $classes = array_values($classes);

        // Backfill history if empty
        $hasHistory = ClassTeacherHistory::where('teacher_id', $teacher->id)->exists() || 
                      SubjectTeacherHistory::where('teacher_id', $teacher->id)->exists();

        if (!$hasHistory) {
            $currentAYArr = AcademicYear::where('is_current', true)->first();
            $class = SchoolClass::where('class_teacher_id', $teacher->id)->first();
            if ($class) {
                ClassTeacherHistory::firstOrCreate(
                    ['class_id' => $class->id, 'teacher_id' => $teacher->id, 'is_active' => true],
                    ['academic_year_id' => $currentAYArr ? $currentAYArr->id : null, 'start_date' => now()]
                );
            }

            $subjectTeachers = DB::table('class_subject_teacher')->where('teacher_id', $teacher->id)->get();
            foreach ($subjectTeachers as $ast) {
                SubjectTeacherHistory::firstOrCreate(
                    ['class_id' => $ast->class_id, 'subject_id' => $ast->subject_id, 'teacher_id' => $teacher->id, 'is_active' => true],
                    ['academic_year_id' => $currentAYArr ? $currentAYArr->id : null, 'start_date' => now()]
                );
            }
        }

        // Fetch History
        $classHistory = ClassTeacherHistory::with(['schoolClass', 'academicYear'])
            ->where('teacher_id', $teacher->id)
            ->orderBy('start_date', 'desc')
            ->get();

        $subjectHistory = SubjectTeacherHistory::with(['schoolClass', 'subject', 'academicYear'])
            ->where('teacher_id', $teacher->id)
            ->orderBy('start_date', 'desc')
            ->get();

        return response()->json([
            'status' => true,
            'message' => 'Teacher profile fetched successfully',
            'data' => [
                'id' => $teacher->id,
                'name' => $teacher->name,
                'email' => $teacher->email,
                'phone' => $teacher->phone,
                'class_teacher_of' => $teacher->classTeacherOf->id ?? null,
                'class_name' => $teacher->classTeacherOf->name ?? null,
                'class_section' => $teacher->classTeacherOf->section ?? null,
                'roles' => $teacher->roles->map(function ($role) {
                    return [
                        'id' => $role->id,
                        'role_name' => $role->role_name,
                    ];
                }),
                'classes' => $classes,
                'class_history' => $classHistory,
                'subject_history' => $subjectHistory
            ]
        ]);
    }

    public function history($domain, $id)
    {
        $teacher = Teacher::findOrFail($id);
        $currentAYArr = AcademicYear::where('is_current', true)->first();

        // Check if backfill is needed (if no entries exist in either history table for this teacher)
        $hasHistory = ClassTeacherHistory::where('teacher_id', $id)->exists() || 
                      SubjectTeacherHistory::where('teacher_id', $id)->exists();

        if (!$hasHistory) {
            // Backfill class teacher assignment
            $class = SchoolClass::where('class_teacher_id', $id)->first();
            if ($class) {
                ClassTeacherHistory::firstOrCreate(
                    ['class_id' => $class->id, 'teacher_id' => $id, 'is_active' => true],
                    ['academic_year_id' => $currentAYArr ? $currentAYArr->id : null, 'start_date' => now()]
                );
            }

            // Backfill subject teacher assignments
            $subjectTeachers = DB::table('class_subject_teacher')->where('teacher_id', $id)->get();
            foreach ($subjectTeachers as $ast) {
                SubjectTeacherHistory::firstOrCreate(
                    ['class_id' => $ast->class_id, 'subject_id' => $ast->subject_id, 'teacher_id' => $id, 'is_active' => true],
                    ['academic_year_id' => $currentAYArr ? $currentAYArr->id : null, 'start_date' => now()]
                );
            }
        }

        $classHistory = ClassTeacherHistory::with(['schoolClass', 'academicYear'])
            ->where('teacher_id', $id)
            ->orderBy('start_date', 'desc')
            ->get();

        $subjectHistory = SubjectTeacherHistory::with(['schoolClass', 'subject', 'academicYear'])
            ->where('teacher_id', $id)
            ->orderBy('start_date', 'desc')
            ->get();

        return response()->json([
            'status' => true,
            'message' => 'Teacher assignment history fetched successfully',
            'data' => [
                'teacher' => [
                    'id' => $teacher->id,
                    'name' => $teacher->name,
                ],
                'class_history' => $classHistory,
                'subject_history' => $subjectHistory
            ]
        ]);
    }
}