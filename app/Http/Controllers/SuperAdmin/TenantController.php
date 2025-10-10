<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rules\Password;

class TenantController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $tenants = Tenant::all();

        if ($tenants->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => "No School Found"
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'All School Fetched Successfully',
            'schools' => $tenants
        ], 200);

    }



    /**
     * Store a newly created resource in storage.
     */
    //? Create new School
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'domain' => 'required|string|max:255|unique:domains,domain',
            'password' => ['required', Password::defaults()],
            'phone' => 'required|string|max:15'
        ]);

        $tenant = Tenant::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'domain' => $validated['domain'],
            'password' => bcrypt($validated['password']),
        ]);
        $tenant->domains()->create([
            'domain' => $validated['domain']
        ]);

        tenancy()->initialize($tenant);

        \Artisan::call('migrate', [
            '--path' => 'database/migrations/tenant', // your tenant migrations
            '--force' => true
        ]);

        // Run Sanctum migration in tenant DB
        \Artisan::call('migrate', [
            '--path' => 'vendor/laravel/sanctum/database/migrations',
            '--database' => 'tenant', // ensure connection switches
            '--force' => true
        ]);

        \App\Models\User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => bcrypt($validated['password']),
            'phone' => $validated['phone']

        ]);

        return response()->json([
            'status' => true,
            'message' => "New School Registered SuccessFully",
            'data' => $tenant
        ]);


    }

    /**
     * Display the specified resource.
     */
    public function show(Tenant $tenant)
    {
        if (!$tenant) {
            return response()->json([
                'status' => false,
                'message' => 'School not found'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'School details fetched successfully',
            'data' => $tenant
        ], 200);
    }



    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Tenant $tenant)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|max:255|unique:tenants,email,' . $tenant->id,
            'password' => ['nullable', 'confirmed', Password::defaults()],
        ]);

        if (isset($validated['name'])) {
            $tenant->name = $validated['name'];
        }

        if (isset($validated['email'])) {
            $tenant->email = $validated['email'];
        }

        if (!empty($validated['password'])) {
            $tenant->password = bcrypt($validated['password']);
        }

        $tenant->save();

        return response()->json([
            'status' => true,
            'message' => "School updated successfully",
            'school' => $tenant
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Tenant $tenant)
    {
        // Delete tenant database
        $dbName = $tenant->database;
        $tenant->delete();

        // Optional: Drop tenant database manually if needed
        \DB::statement("DROP DATABASE IF EXISTS `$dbName`");

        return response()->json([
            'status' => true,
            'message' => "School deleted successfully"
        ], 200);
    }
}



