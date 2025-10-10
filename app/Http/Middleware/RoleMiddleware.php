<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string[]  ...$roles  // role(s) allowed, comma separated
     */
    public function handle(Request $request, Closure $next, ...$roles)
    {
        $user = $request->user(); // This is the tenant user

        if (!$user) {
            return response()->json(['status' => false, 'message' => 'Unauthenticated'], 401);
        }
          if (!$user || !in_array($user->role, $roles)) {
            // If user not logged in or role not allowed
            abort(403, 'Unauthorized action.');
        }

        return $next($request);
    }
}
