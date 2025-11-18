<?php

namespace App\Http\Controllers\Government;
use App\Models\Gov\Government;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class GovernmentController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:governments,email',
            'phone' => 'required|string|max:20',
            'password' => 'required|string|min:6',
            'local_body_id' => 'required|exists:local_bodies,id',
        ]);

        $government = Government::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
            'local_body_id' => $request->local_body_id,
        ]);
        $token = $government->createToken('gov-token')->plainTextToken;

        return response()->json([
            'message' => 'Account registered successfully',
            'government' => $government,
            'token'=> $token
        ]);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:governments,email',
            'password' => 'required|string',
        ]);

        $government = Government::where('email', $request->email)->first();

        if (!$government || !Hash::check($request->password, $government->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Generate token if using API token auth
        $token = $government->createToken('gov-token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'token' => $token,
            'government' => $government,
        ]);
    }
    public function updateProfile(Request $request)
    {
        $government = $request->user(); // Authenticated government

        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|unique:governments,email,' . $government->id,
            'phone' => 'sometimes|required|string|max:20',
            'password' => 'sometimes|nullable|string|min:6|confirmed',
            'local_body_id' => 'sometimes|required|exists:local_bodies,id',
        ]);

        $government->update([
            'name' => $request->name ?? $government->name,
            'email' => $request->email ?? $government->email,
            'phone' => $request->phone ?? $government->phone,
            'password' => $request->password ? Hash::make($request->password) : $government->password,
            'local_body_id' => $request->local_body_id ?? $government->local_body_id,
        ]);

        return response()->json([
            'message' => 'Profile updated successfully',
            'government' => $government,
        ]);
    }

    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:governments,email',
        ]);

        $status = Password::sendResetLink(
            $request->only('email')
        );

        return $status === Password::RESET_LINK_SENT
            ? response()->json(['message' => 'Reset link sent to your email'])
            : response()->json(['message' => 'Unable to send reset link'], 500);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email|exists:governments,email',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($government, $password) {
                $government->password = Hash::make($password);
                $government->save();
            }
        );

        return $status === Password::PASSWORD_RESET
            ? response()->json(['message' => 'Password reset successfully'])
            : response()->json(['message' => 'Failed to reset password'], 500);
    }
}
