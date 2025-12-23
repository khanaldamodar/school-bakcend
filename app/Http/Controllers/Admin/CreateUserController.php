<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LoginAttempt;
use App\Models\LoginHistory;
use App\Models\Tenant;
use App\Models\User;
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
        // Validate login input
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
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

        // Find user in tenant DB
        $user = User::where('email', $credentials['email'])->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid email or password',
            ], 401);
        }

        // Clear login attempts on successful login
        LoginAttempt::clearAttempts($credentials['email']);

        // Optionally generate a token (if using Laravel Sanctum / Passport)
        $token = $user->createToken('tenant-api-token')->plainTextToken;

        // Record login history
        LoginHistory::recordLogin(
            $user->id,
            $request->ip(),
            $request->userAgent()
        );

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

        return response()->json([
            'status' => true,
            'message' => "Logout Successfully"
        ], 200);
    }


}
