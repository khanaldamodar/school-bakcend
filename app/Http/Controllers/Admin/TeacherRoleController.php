<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\TeacherRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TeacherRoleController extends Controller
{
    public function index()
    {
        $roles = TeacherRole::all();
        return response()->json([
            'status' => true,
            'message' => 'Teacher roles fetched successfully',
            'data' => $roles
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'role_name' => 'required|string|max:255|unique:teacher_roles,role_name',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $role = TeacherRole::create($validator->validated());

        return response()->json([
            'status' => true,
            'message' => 'Teacher role created successfully',
            'data' => $role
        ], 201);
    }

    public function update(Request $request, $domain, $id)
    {
        $role = TeacherRole::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'role_name' => 'required|string|max:255|unique:teacher_roles,role_name,' . $id,
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $role->update($validator->validated());

        return response()->json([
            'status' => true,
            'message' => 'Teacher role updated successfully',
            'data' => $role
        ]);
    }

    public function destroy($domain, $id)
    {
        $role = TeacherRole::findOrFail($id);

        // Check if role is assigned to any teacher
        if ($role->teachers()->count() > 0) {
            return response()->json([
                'status' => false,
                'message' => 'Cannot delete role assigned to teachers',
            ], 400);
        }

        $role->delete();

        return response()->json([
            'status' => true,
            'message' => 'Teacher role deleted successfully'
        ]);
    }
}
