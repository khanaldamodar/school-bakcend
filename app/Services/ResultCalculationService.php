<?php

namespace App\Services;

use App\Models\Admin\Result;
use App\Models\Admin\ResultSetting;
use App\Models\Admin\Term;
use App\Models\Admin\AcademicYear;
use Exception;

class ResultCalculationService
{
    /**
     * Get the school-wide ResultSetting
     * 
     * @return ResultSetting
     * @throws Exception
     */
    public function getResultSetting(): ResultSetting
    {
        $resultSetting = ResultSetting::with('terms')->first();
        
        if (!$resultSetting) {
            throw new Exception('Result Setting is not configured. Please configure it first before creating results.');
        }
        
        return $resultSetting;
    }

    /**
     * Get the current academic year
     * 
     * @return AcademicYear|null
     */
    public function getCurrentAcademicYear(): ?AcademicYear
    {
        return AcademicYear::current();
    }

    /**
     * Validate that ResultSetting is configured
     * 
     * @return bool
     */
    public function validateResultSetting(): bool
    {
        return ResultSetting::exists();
    }

    /**
     * Check if activities can be added for a specific term
     * 
     * @param int $termId
     * @param ResultSetting $resultSetting
     * @return bool
     */
    public function canAddActivities(int $termId, ResultSetting $resultSetting): bool
    {
        // If evaluation_per_term is true, activities can be added for any term
        if ($resultSetting->evaluation_per_term) {
            return true;
        }
        
        // If evaluation_per_term is false, activities can only be added for the last term
        return $this->isLastTerm($termId, $resultSetting);
    }

    /**
     * Check if the given term is the last term
     * 
     * @param int $termId
     * @param ResultSetting $resultSetting
     * @return bool
     */
    public function isLastTerm(int $termId, ResultSetting $resultSetting): bool
    {
        $terms = $resultSetting->terms()->orderBy('id')->get();
        
        if ($terms->isEmpty()) {
            return false;
        }
        
        $lastTerm = $terms->last();
        return $lastTerm->id === $termId;
    }

    /**
     * Calculate percentage
     * 
     * @param float $obtained
     * @param float $total
     * @return float
     */
    public function calculatePercentage(float $obtained, float $total): float
    {
        if ($total <= 0) {
            return 0;
        }
        
        return round(($obtained / $total) * 100, 2);
    }

    /**
     * Calculate GPA on 4.0 scale
     * 
     * @param float $obtained
     * @param float $total
     * @return float
     */
    public function calculateGPA(float $obtained, float $total): float
    {
        if ($total <= 0) {
            return 0;
        }
        
        return round(($obtained / $total) * 4, 2);
    }

    /**
     * Calculate result based on ResultSetting configuration
     * 
     * @param Result $result
     * @param float $totalObtained
     * @param float $totalMax
     * @param ResultSetting $resultSetting
     * @return array ['gpa' => float, 'percentage' => float|null]
     */
    public function calculateResult(Result $result, float $totalObtained, float $totalMax, ResultSetting $resultSetting): array
    {
        $gpa = $this->calculateGPA($totalObtained, $totalMax);
        $percentage = null;
        
        // Calculate percentage if result_type is percentage
        if ($resultSetting->result_type === 'percentage') {
            $percentage = $this->calculatePercentage($totalObtained, $totalMax);
        }
        
        return [
            'gpa' => $gpa,
            'percentage' => $percentage
        ];
    }

    /**
     * Calculate weighted final result for a student
     * 
     * @param int $studentId
     * @param int $classId
     * @param ResultSetting $resultSetting
     * @return array|null
     */
    public function calculateWeightedFinalResult(int $studentId, int $classId, ResultSetting $resultSetting, ?int $academicYearId = null): ?array
    {
        // Only calculate if calculation_method is weighted
        if ($resultSetting->calculation_method !== 'weighted') {
            return null;
        }

        // Use current academic year if not provided
        if (!$academicYearId) {
            $currentYear = $this->getCurrentAcademicYear();
            $academicYearId = $currentYear?->id;
        }

        if (!$academicYearId) {
            return null; // Cannot calculate without academic year context
        }

        // Get all terms
        $terms = $resultSetting->terms()->orderBy('id')->get();
        
        if ($terms->isEmpty()) {
            return null;
        }

        // Get results for all terms
        $termResults = [];
        $totalWeight = 0;
        $weightedSum = 0;

        foreach ($terms as $term) {
            // Get average result for this term
            $results = Result::where('student_id', $studentId)
                ->where('class_id', $classId)
                ->where('term_id', $term->id)
                ->where('academic_year_id', $academicYearId)
                ->get();

            if ($results->isEmpty()) {
                // If any term is missing, cannot calculate final result
                return null;
            }

            // Calculate average based on result_type
            if ($resultSetting->result_type === 'percentage') {
                $average = $results->avg('percentage');
            } else {
                $average = $results->avg('gpa');
            }

            $weight = $term->weight ?? 0;
            $totalWeight += $weight;
            $weightedSum += ($average * $weight);

            $termResults[] = [
                'term_id' => $term->id,
                'term_name' => $term->name,
                'average' => round($average, 2),
                'weight' => $weight
            ];
        }

        // Calculate final weighted result
        $finalResult = $totalWeight > 0 ? round($weightedSum / $totalWeight, 2) : 0;

        return [
            'final_result' => $finalResult,
            'result_type' => $resultSetting->result_type,
            'calculation_method' => 'weighted',
            'term_results' => $termResults,
            'total_weight' => $totalWeight
        ];
    }

    /**
     * Validate term exists in ResultSetting
     * 
     * @param int $termId
     * @param ResultSetting $resultSetting
     * @return bool
     */
    public function validateTerm(int $termId, ResultSetting $resultSetting): bool
    {
        return $resultSetting->terms()->where('id', $termId)->exists();
    }
}
