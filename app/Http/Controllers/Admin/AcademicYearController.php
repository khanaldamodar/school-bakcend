<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\AcademicYear;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AcademicYearController extends Controller
{
    /**
     * Display a listing of academic years
     */
    public function index(Request $request)
    {
        $query = AcademicYear::query()->orderBy('start_date', 'desc');

        if ($request->has('is_current') && $request->is_current !== null) {
            $query->where('is_current', $request->is_current);
        }

        $academicYears = $query->paginate($request->get('per_page', 10));

        return response()->json([
            'status' => true,
            'message' => 'Academic years fetched successfully',
            'data' => $academicYears->items(),
            'meta' => [
                'current_page' => $academicYears->currentPage(),
                'last_page' => $academicYears->lastPage(),
                'per_page' => $academicYears->perPage(),
                'total' => $academicYears->total(),
            ],
        ]);
    }

    /**
     * Store a newly created academic year
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:academic_years,name',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'is_current' => 'nullable|boolean',
            'description' => 'nullable|string',
        ]);

        DB::beginTransaction();

        try {
            // If this is set as current, unset all others
            if ($validated['is_current'] ?? false) {
                AcademicYear::where('is_current', true)->update(['is_current' => false]);
            }

            $academicYear = AcademicYear::create($validated);

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Academic year created successfully',
                'data' => $academicYear
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Academic year creation failed', ['error' => $e->getMessage()]);
            return response()->json([
                'status' => false,
                'message' => 'Failed to create academic year',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified academic year
     */
    public function show($domain, string $id)
    {
        $academicYear = AcademicYear::with('studentHistories.student', 'studentHistories.class')
            ->findOrFail($id);

        return response()->json([
            'status' => true,
            'message' => 'Academic year fetched successfully',
            'data' => $academicYear
        ]);
    }

    /**
     * Update the specified academic year
     */
    public function update(Request $request, $domain, string $id)
    {
        $academicYear = AcademicYear::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:academic_years,name,' . $id,
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'is_current' => 'nullable|boolean',
            'description' => 'nullable|string',
        ]);

        DB::beginTransaction();

        try {
            // If this is set as current, unset all others
            if ($validated['is_current'] ?? false) {
                AcademicYear::where('id', '!=', $id)
                    ->where('is_current', true)
                    ->update(['is_current' => false]);
            }

            $academicYear->update($validated);

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Academic year updated successfully',
                'data' => $academicYear
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Academic year update failed', ['error' => $e->getMessage()]);
            return response()->json([
                'status' => false,
                'message' => 'Failed to update academic year',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified academic year
     */
    public function destroy($domain, string $id)
    {
        $academicYear = AcademicYear::findOrFail($id);

        if ($academicYear->is_current) {
            return response()->json([
                'status' => false,
                'message' => 'Cannot delete the current academic year',
            ], 400);
        }

        $academicYear->delete();

        return response()->json([
            'status' => true,
            'message' => 'Academic year deleted successfully',
        ]);
    }

    /**
     * Set an academic year as current
     */
    public function setCurrent($domain, string $id)
    {
        $academicYear = AcademicYear::findOrFail($id);

        DB::beginTransaction();

        try {
            // Unset all others
            AcademicYear::where('id', '!=', $id)->update(['is_current' => false]);
            
            // Set this one as current
            $academicYear->update(['is_current' => true]);

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Academic year set as current successfully',
                'data' => $academicYear
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Failed to set current academic year', ['error' => $e->getMessage()]);
            return response()->json([
                'status' => false,
                'message' => 'Failed to set current academic year',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get the current academic year
     */
    public function current()
    {
        $academicYear = AcademicYear::current();

        if (!$academicYear) {
            return response()->json([
                'status' => false,
                'message' => 'No current academic year set',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Current academic year fetched successfully',
            'data' => $academicYear
        ]);
    }
}
