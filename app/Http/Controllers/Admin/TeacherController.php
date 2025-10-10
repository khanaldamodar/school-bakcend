<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\SchoolClass;
use App\Models\Admin\Teacher;
use App\Models\Admin\Subject;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class TeacherController extends Controller
{
    public function index()
    {
        $teachers = Teacher::with(['subjects:id,name', 'classTeacherOf:id,name,class_teacher_id'])->get();

        return response()->json([
            'status' => true,
            'message' => 'Teachers fetched successfully',
            'data' => $teachers
        ]);
    }


    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'required|string|unique:users,phone',
            'qualification' => 'nullable|string',
            'address' => 'nullable|string',
            'subject_ids' => 'nullable|array',
            'subject_ids.*' => 'exists:subjects,id',
            'class_teacher_of' => 'nullable|exists:classes,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();

        DB::beginTransaction();

        try {
            //  Create user for authentication with phone as initial password
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'password' => Hash::make($data['phone']), // password = phone number initially
                'role' => 'teacher'
            ]);

            // Create teacher profile
            $teacher = Teacher::create([
                'user_id' => $user->id,
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'qualification' => $data['qualification'] ?? null,
                'address' => $data['address'] ?? null,
                'class_teacher_of' => $data['class_teacher_of'] ?? null
            ]);

            //  Sync subjects
            if (!empty($data['subject_ids'])) {
                $teacher->subjects()->sync($data['subject_ids']);
            }

            // Assign class teacher if provided
            if (!empty($data['class_teacher_of'])) {
                $class = \App\Models\Admin\SchoolClass::find($data['class_teacher_of']);
                $class->class_teacher_id = $teacher->id;
                $class->save();
            }

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Teacher created successfully',
                'data' => $teacher->load('subjects', 'classTeacherOf', 'user')
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => 'Something went wrong',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function show($domain, $id)
    {
        $teacher = Teacher::with(['subjects:id,name', 'classTeacherOf:id,name,class_teacher_id'])->findOrFail($id);

        return response()->json([
            'status' => true,
            'message' => 'Teacher fetched successfully',
            'data' => $teacher
        ]);
    }

    public function update(Request $request, $domain, $id)
    {
        $teacher = Teacher::findOrFail($id);

        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:teachers,email,' . $teacher->id,
            'phone' => 'sometimes|string|unique:teachers,phone,' . $teacher->id,
            'qualification' => 'nullable|string',
            'address' => 'nullable|string',
            'employee_code' => 'nullable|string|unique:teachers,employee_code,' . $teacher->id,
            'subject_ids' => 'nullable|array',
            'subject_ids.*' => 'exists:subjects,id',
            'class_teacher_of' => 'nullable|exists:classes,id'
        ]);

        $teacher->update($data);

        if (isset($data['subject_ids'])) {
            $teacher->subjects()->sync($data['subject_ids']);
        }

        if (isset($data['class_teacher_of'])) {
            $class = \App\Models\Admin\SchoolClass::find($data['class_teacher_of']);
            $class->class_teacher_id = $teacher->id;
            $class->save();
        }

        return response()->json([
            'status' => true,
            'message' => 'Teacher updated successfully',
            'data' => $teacher->load('subjects', 'classTeacherOf')
        ]);
    }

    public function destroy($domain, $id)
    {
        $teacher = Teacher::findOrFail($id);
        $teacher->delete();

        return response()->json([
            'status' => true,
            'message' => 'Teacher deleted successfully'
        ]);
    }

public function me(Request $request)
{
    $user = $request->user();

    $teacher = Teacher::with(['classTeacherOf', 'classSubjects'])->where('user_id', $user->id)->first();

    if (!$teacher) {
        return response()->json([
            'status' => false,
            'message' => 'Teacher profile not found'
        ], 404);
    }

    // Group subjects by class_id
    $classes = [];
    
    foreach ($teacher->classSubjects as $subject) {
        $classId = $subject->pivot->class_id;

        if (!isset($classes[$classId])) {
            $cls = SchoolClass::find($classId); // fetch class info
            $classes[$classId] = [
                'id' => $cls->id,
                'name' => $cls->name,
                'is_class_teacher' => $teacher->class_teacher_of == $cls->id,
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
            'classes' => $classes,
        ]
    ]);
}












}
