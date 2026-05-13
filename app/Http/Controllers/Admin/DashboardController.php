<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\AcademicYear;
use App\Models\Admin\ParentModel;
use App\Services\SchoolStatisticsService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        protected SchoolStatisticsService $statisticsService
    ) {}

    /**
     * Centralized dashboard endpoint for all statistics.
     * GET tenants/{domain}/admin/dashboard/stats
     *
     * Returns:
     * - Widget counts (students, teachers, etc.)
     * - Detailed academic performance
     * - Class-wise enrollment
     * - Optional year-over-year comparisons
     * - Optional teacher-class mapping
     */
    public function stats(Request $request, string $domain)
    {
        $validated = $request->validate([
            'academic_year_id' => 'nullable|integer|exists:academic_years,id',
            'compare_year_id'  => 'nullable|integer|exists:academic_years,id',
            'gender'           => 'nullable|string|in:male,female,other',
            'is_tribe'         => 'nullable|boolean',
            'is_disabled'      => 'nullable|boolean',
            'ethnicity'        => 'nullable|string',
            'subject_id'       => 'nullable|integer|exists:subjects,id',
            'class_id'         => 'nullable|integer|exists:classes,id',
            'include_teachers' => 'nullable|boolean',
        ]);

        // Resolve the primary academic year
        [$year, $usedFallback] = $this->resolveYear($validated['academic_year_id'] ?? null);

        if (!$year) {
            return response()->json([
                'status'  => false,
                'message' => 'No academic years exist for this school.',
            ], 422);
        }

        // Build full statistics
        $filters = $request->only(['gender', 'is_tribe', 'is_disabled', 'ethnicity', 'subject_id', 'class_id']);
        $statsData = $this->statisticsService->build($year, $filters);

        // Calculate widget counts (using the stats data to ensure consistency)
        $widgets = [
            'total_teachers'        => $statsData['summary']['total_teachers'] ?? 0,
            'total_students'        => $statsData['summary']['total_students'] ?? 0,
            'total_classes'         => $statsData['summary']['total_classes'] ?? 0,
            'total_parents'         => ParentModel::count(),
            'passed_students_count' => $statsData['exam_overall']['passed_count'] ?? 0,
            'pass_percentage'       => $statsData['exam_overall']['pass_rate_pct'] ?? 0,
        ];

        // Handle Comparison
        if ($request->has('compare_year_id')) {
            $compareYear = AcademicYear::find($request->query('compare_year_id'));
            if ($compareYear) {
                $compareData = $this->statisticsService->build($compareYear, $filters);
                $statsData['comparison'] = [
                    'year'   => $compareYear->name,
                    'data'   => $compareData,
                    'trends' => $this->statisticsService->calculateTrends($statsData, $compareData),
                ];
            }
        }

        // Strip teacher mapping if not requested (keeps payload smaller)
        if (!$request->boolean('include_teachers')) {
            unset($statsData['teacher_mapping']);
        }

        if ($usedFallback) {
            $statsData['meta']['year_resolution_note'] = 'Using latest year as fallback.';
        }

        return response()->json([
            'status'  => true,
            'message' => 'Dashboard statistics fetched successfully',
            'data'    => array_merge($statsData, ['widgets' => $widgets]),
        ]);
    }

    /**
     * Resolve AcademicYear: Requested -> Current -> Latest
     */
    private function resolveYear(?int $academicYearId): array
    {
        if ($academicYearId) {
            return [AcademicYear::find($academicYearId), false];
        }

        $year = AcademicYear::current();
        if (!$year) {
            $year = AcademicYear::orderByDesc('start_date')->first();
            return [$year, $year !== null];
        }

        return [$year, false];
    }
}
