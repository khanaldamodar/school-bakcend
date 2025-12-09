<?php

namespace App\Http\Middleware;

use App\Models\LoginAttempt;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RateLimitLogin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only apply to login requests
        if ($request->isMethod('post') && $request->has('email')) {
            $email = $request->input('email');
            $ipAddress = $request->ip();

            // Clean up old attempts first
            LoginAttempt::clearOldAttempts($email);

            // Check if the email is currently locked out
            if (LoginAttempt::isLockedOut($email)) {
                $timeRemaining = LoginAttempt::getLockoutTimeRemaining($email);
                $minutesRemaining = ceil($timeRemaining / 60);

                return response()->json([
                    'status' => false,
                    'message' => 'Too many login attempts. Please try again later.',
                    'lockout_time_remaining' => $timeRemaining,
                    'lockout_minutes_remaining' => $minutesRemaining,
                    'error' => 'account_locked'
                ], 429); // 429 Too Many Requests
            }

            // Record this attempt (will be cleared on successful login in controller)
            LoginAttempt::recordAttempt($email, $ipAddress);
        }

        return $next($request);
    }
}
