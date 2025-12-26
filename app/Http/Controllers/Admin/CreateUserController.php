<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LoginAttempt;
use App\Models\LoginHistory;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreateUserController extends Controller
{
    public function register(Request $request, $domain)
    {


        $validated = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'phone' => 'required|string',
            'role' => 'required',
            'password' => 'required'
        ]);

        if ($validated->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validated->errors(),
            ], 422);
        }

        $credentials = $validated->validated();

        $data = User::create([
            'name' => $credentials['name'],
            'email' => $credentials['email'],
            'password' => bcrypt($credentials['password']),
            'phone' => $credentials['phone'],
            'role' => $credentials['role']
        ]);

        TenantLogger::logAuth("New user registered: {$data->name} ({$data->role})", [
            'id' => $data->id,
            'email' => $data->email,
            'role' => $data->role
        ]);

        return response()->json([
            'status' => true,
            'message' => 'User Created Successfully',
            'user' => $data
        ], 200);
    }


    public function show(Request $request, $domain)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => "User not Authenticated"
            ], 401);
        }

        return response()->json([
            'status' => true,
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email
            ]
        ]);
    }

    
   
    public function login(Request $request, $domain)
    {
        // Validate login input - accept either email or student_id
        $validator = Validator::make($request->all(), [
            'email' => 'nullable|string',
            'student_id' => 'nullable|string',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $credentials = $validator->validated();
        
        // Ensure at least one identifier is provided
        if (empty($credentials['email']) && empty($credentials['student_id'])) {
            return response()->json([
                'status' => false,
                'message' => 'Please provide either email or student ID',
            ], 422);
        }

        $user = null;
        $isStudentLogin = false;

        // Check if logging in with student_id
        if (!empty($credentials['student_id'])) {
            $isStudentLogin = true;
            
            // Find student by student_id
            $student = \App\Models\Admin\Student::where('student_id', $credentials['student_id'])->first();
            
            if (!$student) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid student ID or password',
                ], 401);
            }
            
            // For students, password should match student_id
            if ($credentials['password'] !== $student->student_id) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid student ID or password',
                ], 401);
            }
            
            // Find or create user account for this student
            if ($student->user_id) {
                $user = User::find($student->user_id);
            }
            
            
            // If no user exists, create one
            if (!$user) {
                // Ensure we always have a valid email (never null)
                $userEmail = $student->email;
                if (empty($userEmail)) {
                    $userEmail = $student->student_id . '@student.local';
                }
                
                $user = User::create([
                    'name' => trim($student->first_name . ' ' . ($student->middle_name ?? '') . ' ' . ($student->last_name ?? '')),
                    'email' => $userEmail,
                    'password' => Hash::make($student->student_id), // Password is student_id
                    'phone' => $student->phone,
                    'role' => 'student'
                ]);
                
                // Link user to student
                $student->user_id = $user->id;
                $student->save();
            }
        } else {
            // Email-based login (for admin, teacher, parent)
            $user = User::where('email', $credentials['email'])->first();

            if (!$user || !Hash::check($credentials['password'], $user->password)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid email or password',
                ], 401);
            }
        }

        // Clear login attempts on successful login
        LoginAttempt::clearAttempts($credentials['email'] ?? $credentials['student_id']);

        // Generate token
        $token = $user->createToken('tenant-api-token')->plainTextToken;

        // Record login history
        LoginHistory::recordLogin(
            $user->id,
            $request->ip(),
            $request->userAgent()
        );

        TenantLogger::logAuth("User logged in: {$user->name}", [
            'id' => $user->id,
            'email' => $user->email,
            'login_type' => $isStudentLogin ? 'student_id' : 'email'
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Login successful',
            'data' => [
                'user' => $user,
                'token' => $token,
            ],
        ]);
    }

     public function logout(Request $request, $domain)
    {
        $user = $request->user();
        
        // Record logout in history
        LoginHistory::recordLogout($user->id);

        $user->currentAccessToken()->delete();

        TenantLogger::logAuth("User logged out: {$user->name}", [
            'id' => $user->id,
            'email' => $user->email
        ]);

        return response()->json([
            'status' => true,
            'message' => "Logout Successfully"
        ], 200);
    }


}
