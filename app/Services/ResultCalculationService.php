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
        if ($terms->isEmpty()) return null;

        // Get all subjects for this student in this class/year
        $subjectIds = Result::where('student_id', $studentId)
            ->where('class_id', $classId)
            ->where('academic_year_id', $academicYearId)
            ->pluck('subject_id')
            ->unique();

        if ($subjectIds->isEmpty()) return null;

        $subjectResults = [];
        $totalWeight = 0;
        $overallWeightedGPA = 0;
        $overallWeightedPercentage = 0;

        foreach ($subjectIds as $subjectId) {
            $weightedSubjectGPA = 0;
            $weightedSubjectPercentage = 0;
            $termWeightSum = 0;
            $subjectName = '';

            foreach ($terms as $term) {
                $result = Result::with('subject')
                    ->where('student_id', $studentId)
                    ->where('class_id', $classId)
                    ->where('subject_id', $subjectId)
                    ->where('term_id', $term->id)
                    ->where('academic_year_id', $academicYearId)
                    ->first();

                if ($result) {
                    $subjectName = $result->subject->name;
                    $weight = $term->weight ?? 0;
                    $weightedSubjectGPA += ($result->gpa * $weight);
                    $weightedSubjectPercentage += ($result->percentage * $weight);
                    $termWeightSum += $weight;
                }
            }

            if ($termWeightSum > 0) {
                $finalSubjectGPA = round($weightedSubjectGPA / $termWeightSum, 2);
                $finalSubjectPercentage = round($weightedSubjectPercentage / $termWeightSum, 2);
                
                $subjectResults[$subjectId] = [
                    'subject_id' => $subjectId,
                    'subject_name' => $subjectName,
                    'weighted_gpa' => $finalSubjectGPA,
                    'weighted_percentage' => $finalSubjectPercentage
                ];
            }
        }

        // Calculate overall weighted averages
        if (empty($subjectResults)) return null;

        foreach ($terms as $term) {
            $weight = $term->weight ?? 0;
            $totalWeight += $weight;

            $termResults = Result::where('student_id', $studentId)
                ->where('class_id', $classId)
                ->where('term_id', $term->id)
                ->where('academic_year_id', $academicYearId)
                ->get();

            if (!$termResults->isEmpty()) {
                $termAvgGPA = $termResults->avg('gpa');
                $termAvgPercentage = $termResults->avg('percentage');
                
                $overallWeightedGPA += ($termAvgGPA * $weight);
                $overallWeightedPercentage += ($termAvgPercentage * $weight);
            }
        }

        $finalGPA = $totalWeight > 0 ? round($overallWeightedGPA / $totalWeight, 2) : 0;
        $finalPercentage = $totalWeight > 0 ? round($overallWeightedPercentage / $totalWeight, 2) : 0;

        return [
            'final_result' => ($resultSetting->result_type === 'percentage') ? $finalPercentage : $finalGPA,
            'final_gpa' => $finalGPA,
            'final_percentage' => $finalPercentage,
            'result_type' => $resultSetting->result_type,
            'calculation_method' => 'weighted',
            'subject_results' => array_values($subjectResults),
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
