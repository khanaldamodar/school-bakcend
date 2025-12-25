<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\Admin\Student;
use App\Models\Admin\Teacher;
use Illuminate\Http\Request;

class SchoolDataController extends Controller
{
    /**
     * View all deleted students from a specific school.
     */
    public function getDeletedStudents($tenantId)
    {
        $tenant = Tenant::find($tenantId);

        if (!$tenant) {
            return response()->json([
                'status' => false,
                'message' => 'School not found'
            ], 404);
        }

        $deletedStudents = $tenant->run(function () {
            // we use withoutGlobalScope('not_deleted') because students model has a global scope
            return Student::withoutGlobalScope('not_deleted')
                ->where('is_deleted', true)
                ->with(['class', 'user'])
                ->get();
        });

        return response()->json([
            'status' => true,
            'message' => 'Deleted students fetched successfully',
            'data' => $deletedStudents
        ]);
    }

    /**
     * View all deleted teachers from a specific school.
     */
    public function getDeletedTeachers($tenantId)
    {
        $tenant = Tenant::find($tenantId);

        if (!$tenant) {
            return response()->json([
                'status' => false,
                'message' => 'School not found'
            ], 404);
        }

        $deletedTeachers = $tenant->run(function () {
            return Teacher::withoutGlobalScope('not_deleted')
                ->where('is_deleted', true)
                ->with('user')
                ->get();
        });

        return response()->json([
            'status' => true,
            'message' => 'Deleted teachers fetched successfully',
            'data' => $deletedTeachers
        ]);
    }
    
    /**
     * Get overview stats for Super Admin Dashboard
     */
    public function getStats()
    {
        $totalSchools = Tenant::count();
        $totalLocalBodies = \App\Models\LocalBody::count();
        $totalSmsBalance = Tenant::sum('sms_balance');
        
        return response()->json([
            'status' => true,
            'data' => [
                'total_schools' => $totalSchools,
                'total_local_bodies' => $totalLocalBodies,
                'total_sms_balance' => $totalSmsBalance
            ]
        ]);
    }
}
