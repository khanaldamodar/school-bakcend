<?php
namespace App\Http\Controllers\Admin;

use App\Helpers\ImageUploadHelper;
use App\Http\Controllers\Controller;
use App\Models\Admin\SchoolClass;
use App\Models\Admin\Teacher;
use App\Models\Admin\Subject;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Cloudinary\Cloudinary;

class TeacherController extends Controller
{



    //     /**
//  * Handle teacher image upload.
//  *
//  * @param \Illuminate\Http\Request $request
//  * @param string|null $oldImagePath
//  * @return string|null
//  */
// protected function handleImageUpload(Request $request, $oldImagePath = null)
// {
//     // If no image was sent, return the old one (for updates)
//     if (!$request->hasFile('image')) {
//         return $oldImagePath;
//     }

    //     $file = $request->file('image');

    //     // Validate file type (safety check)
//     $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
//     $extension = strtolower($file->getClientOriginalExtension());
//     if (!in_array($extension, $allowedExtensions)) {
//         throw new \Exception('Invalid image type. Only JPG, JPEG, PNG, and WEBP are allowed.');
//     }

    //     // Optional: delete old image if exists
//     if ($oldImagePath && Storage::disk('public')->exists($oldImagePath)) {
//         Storage::disk('public')->delete($oldImagePath);
//     }

    //     // Generate unique filename
//     $fileName = 'teachers/' . uniqid('teacher_') . '.' . $extension;

    //     // Store in /storage/app/public/teachers
//     $path = $file->storeAs('teachers', $fileName, 'public');

    //     return $path;
// }

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
            'is_tribe' => 'required|boolean',
            'image' => 'nullable|file|image|max:2048',
            'gender' => 'required|string',
            'dob' => 'required|date',
            'nationality' => 'required|string',
            'subject_ids' => 'nullable|array',
            'subject_ids.*' => 'exists:subjects,id',
            'class_teacher_of' => 'nullable|exists:classes,id'
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


            // $imageData = ImageUploadHelper::uploadToCloud(
            //     $request->file('image'),
            //     "{$tenantDomain}/teachers"
            // );

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



            // ✅ Create user
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'password' => Hash::make($data['phone']),
                'role' => 'teacher',
            ]);

            // ✅ Create teacher profile
            $teacher = Teacher::create([
                'user_id' => $user->id,
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'qualification' => $data['qualification'] ?? null,
                'address' => $data['address'] ?? null,
                'blood_group' => $data['blood_group'] ?? null,
                'is_disabled' => $data['is_disabled'],
                'is_tribe' => $data['is_tribe'],
                'image' => $imageData['url'] ?? null,
                'cloudinary_id' => $cloudinaryId,
                // 'cloudinary_id' => $uploadedImage['public_id'] ?? null,
                'gender' => $data['gender'],
                'dob' => $data['dob'],
                'nationality' => $data['nationality'],
                'class_teacher_of' => $data['class_teacher_of'] ?? null,
            ]);

            // ✅ Sync subjects
            if (!empty($data['subject_ids'])) {
                $teacher->subjects()->sync($data['subject_ids']);
            }

            // ✅ Assign class teacher
            if (!empty($data['class_teacher_of'])) {
                $class = SchoolClass::find($data['class_teacher_of']);
                $class->class_teacher_id = $teacher->id;
                $class->save();
            }

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Teacher created successfully',
                'data' => $teacher->load('subjects', 'classTeacherOf', 'user'),
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
        $teacher = Teacher::with(['subjects:id,name', 'classTeacherOf:id,name,class_teacher_id'])->findOrFail($id);
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
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'phone' => 'required|string|unique:users,phone,' . $user->id,
            'qualification' => 'nullable|string',
            'address' => 'nullable|string',
            'blood_group' => 'nullable|string',
            'is_disabled' => 'required|boolean',
            'is_tribe' => 'required|boolean',
            'image' => 'nullable|file|image|max:2048',
            'gender' => 'required|string',
            'dob' => 'required|date',
            'nationality' => 'required|string',
            'subject_ids' => 'nullable|array',
            'subject_ids.*' => 'exists:subjects,id',
            'class_teacher_of' => 'nullable|exists:classes,id'
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

            // ✅ Handle new image upload
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

            // ✅ Update user
            $user->update([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
            ]);

            // ✅ Update teacher
            $teacher->update([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'qualification' => $data['qualification'] ?? null,
                'address' => $data['address'] ?? null,
                'blood_group' => $data['blood_group'] ?? null,
                'is_disabled' => $data['is_disabled'],
                'is_tribe' => $data['is_tribe'],
                'image' => $imageUrl,
                'cloudinary_id' => $cloudinaryId,
                'gender' => $data['gender'],
                'dob' => $data['dob'],
                'nationality' => $data['nationality'],
                'class_teacher_of' => $data['class_teacher_of'] ?? null,
            ]);

            // ✅ Sync subjects
            if (!empty($data['subject_ids'])) {
                $teacher->subjects()->sync($data['subject_ids']);
            } else {
                $teacher->subjects()->sync([]);
            }

            // ✅ Update class teacher assignment
            if (!empty($data['class_teacher_of'])) {
                $class = SchoolClass::find($data['class_teacher_of']);
                $class->class_teacher_id = $teacher->id;
                $class->save();
            }

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Teacher updated successfully',
                'data' => $teacher->load('subjects', 'classTeacherOf', 'user'),
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

        // Delete image from Cloudinary
        if ($teacher->cloudinary_id) {
            try {
                $cloudinary = new Cloudinary(env('CLOUDINARY_URL'));
                $cloudinary->uploadApi()->destroy($teacher->cloudinary_id);
            } catch (\Exception $e) {
                // Optionally log the error
                \Log::error("Cloudinary image deletion failed: " . $e->getMessage());
            }
        }

        // Delete teacher record
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
