<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
class InitializeTenant
{
    public function handle(Request $request, Closure $next)
    {
        $domainName = $request->route('domain');
        $tenant = Tenant::whereHas('domains', function ($q) use ($domainName) {
            $q->where('domain', $domainName);
        })->first();
        // If the api doesn't hanve the domain 
        if (!$tenant) {
            return response()->json(['status' => false, 'message' => 'Tenant not found'], 404);
        }
        // Initialize tenant database
        tenancy()->initialize($tenant);

        return $next($request);
    }
}
