<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\SuperAdmin\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CentralController extends Controller
{
    public function viewusers()
    {
        $users = User::all();

        if ($users->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => "No Users found",
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => "Users Fetched Successfully",
            'users' => $users
        ], 200);
    }
    public function register(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'name' => "string|required|max:255",
            'email' => "required|email",
            'role'=> "required|string",
            'district' => 'nullable|string',
            'local_bodies'=> 'nullable|string',
            'password' => "required",
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                "message" => "Validation Failed",
                "error" => $validator->error()
            ], 404);
        }

        $data = $validator->validated();

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $data['role'],
            'district' => $data['district'],
            'local_bodies' => $data['local_bodies'],
            'password' => bcrypt($data['password']),
        ]);

        return response()->json([
            'status' => true,
            'message' => 'User registered successfully',
            'user' => $user,
        ], 201);

    }

    public function login(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => "Validation Failed",
                'error' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();

        // Find the correct user in Database
        $user = User::where('email', $data['email'])->first();
        if (!$user || !Hash::check($data['password'], $user->password)) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid email or password',
            ], 401);
        }


        $token = $user->createToken('tenant-api-token')->plainTextToken;

        return response()->json([
            'status' => true,
            'message' => 'Login successful',
            'data' => [
                'user' => $user,
                'token' => $token,
            ],
        ]);


    }

    public function logout(Request $request)
    {

        $request->user()->currentAccessToken()->delete();
        return response()->json([
            'status' => true,
            'message' => "Logout Successfully"
        ], 200);

    }
}
