<?php

namespace App\Services;

use App\Models\Admin\Result;
use App\Models\Admin\ResultSetting;
use App\Models\Admin\AcademicYear;
use Exception;

class ResultCalculationService
{
    /**
     * Get the school-wide ResultSetting for current academic year
     * 
     * @param int|null $academicYearId
     * @return ResultSetting
     * @throws Exception
     */
    public function getResultSetting(?int $academicYearId = null): ResultSetting
    {
        // Try to get result setting for specific academic year first
        if ($academicYearId) {
            $resultSetting = ResultSetting::with('terms')
                ->where('academic_year_id', $academicYearId)
                ->first();
        } else {
            // Fallback to current academic year
            $resultSetting = ResultSetting::current();
        }
        
        // Final fallback to any result setting (backward compatibility)
        if (!$resultSetting) {
            $resultSetting = ResultSetting::with('terms')->first();
        }
        
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
     * Check if practical marks should be included for a specific term
     * 
     * Rules:
     * - If evaluation_per_term = true: Include practical in all terms (both simple and weighted)
     * - If evaluation_per_term = false: Include practical only in last term (both simple and weighted)
     * 
     * This applies to BOTH calculation methods (simple and weighted)
     * 
     * @param int $termId
     * @param ResultSetting $resultSetting
     * @return bool
     */
    public function shouldIncludePractical(int $termId, ResultSetting $resultSetting): bool
    {
        // If evaluation_per_term is true, include practical in all terms
        // This applies to both 'simple' and 'weighted' calculation methods
        if ($resultSetting->evaluation_per_term) {
            return true;
        }
        
        // If evaluation_per_term is false, include practical only in last term
        // This applies to both 'simple' and 'weighted' calculation methods
        return $this->isLastTerm($termId, $resultSetting);
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
     * Calculate GPA on 4.0 scale (NEB Nepal Standard)
     * 
     * @param float $obtained
     * @param float $total
     * @return float
     */
    /**
     * Calculate GPA on 4.0 scale (NEB Nepal Standard)
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
        
        $percentage = ($obtained / $total) * 100;
        
        // NEB Nepal Grade Point Calculation (Standardized)
        if ($percentage >= 90) return 4.0;        // A+
        if ($percentage >= 80) return 3.6;        // A
        if ($percentage >= 70) return 3.2;        // B+
        if ($percentage >= 60) return 2.8;        // B
        if ($percentage >= 50) return 2.4;        // C+
        if ($percentage >= 40) return 2.0;        // C
        if ($percentage >= 35) return 1.6;        // D
        
        return 0.0;  // NG (Non-Graded)
    }

    /**
     * Get Grade from GPA (NEB Nepal Standard)
     * 
     * @param float $gpa
     * @return string
     */
    public function getGradeFromGPA(float $gpa): string
    {
        if ($gpa >= 3.61) return "A+"; // 4.0 is A+
        if ($gpa >= 3.6) return "A+";  
        if ($gpa >= 3.2) return "A";
        if ($gpa >= 2.8) return "B+";
        if ($gpa >= 2.4) return "B";
        if ($gpa >= 2.0) return "C+";
        if ($gpa >= 1.6) return "C";
        if ($gpa >= 1.2) return "D"; // Adjusting for 35-39% which is 1.6 GP
        
        // Simplified mapping based on NEB
        if ($gpa >= 3.6) return "A+";
        if ($gpa >= 3.2) return "A";
        if ($gpa >= 2.8) return "B+";
        if ($gpa >= 2.4) return "B";
        if ($gpa >= 2.0) return "C+";
        if ($gpa >= 1.6) return "C";
        if ($gpa >= 1.6) return "D"; // Nepal uses 1.6 for D
        
        return "NG"; 
    }
    
    /**
     * Get Grade from Percentage directly (More accurate for NEB)
     */
    public function getGradeFromPercentage(float $percentage): string
    {
        if ($percentage >= 90) return "A+";
        if ($percentage >= 80) return "A";
        if ($percentage >= 70) return "B+";
        if ($percentage >= 60) return "B";
        if ($percentage >= 50) return "C+";
        if ($percentage >= 40) return "C";
        if ($percentage >= 35) return "D";
        return "NG";
    }

    /**
     * Get Division from Percentage (NEB Nepal Standard)
     * 
     * @param float $percentage
     * @return string
     */
    public function getDivisionFromPercentage(float $percentage): string
    {
        if ($percentage >= 80) return "Distinction";
        if ($percentage >= 60) return "First Division";
        if ($percentage >= 45) return "Second Division";
        if ($percentage >= 35) return "Third Division";
        
        return "Fail";
    }

    /**
     * Validate Nepal Passing Criteria (35% in theory AND practical separately for new standard, but user said 33%)
     * NEB currently uses 35% for theory and practical separately as of recent updates.
     * User explicitly said 33%, so I will use 33% but allow flexibility.
     * 
     * @param float $theoryObtained
     * @param float $theoryTotal
     * @param float $practicalObtained
     * @param float $practicalTotal
     * @param float $theoryPassMarks
     * @param float $practicalPassMarks
     * @return bool
     */
    public function validateNepalPassingCriteria(
        float $theoryObtained, 
        float $theoryTotal,
        float $practicalObtained = 0,
        float $practicalTotal = 0,
        float $theoryPassMarks = 0, // 0 means use 33% default
        float $practicalPassMarks = 0
    ): bool {
        // Use 33% as absolute minimum if pass marks are not set
        $tPass = $theoryPassMarks > 0 ? $theoryPassMarks : ($theoryTotal * 0.33);
        $pPass = $practicalPassMarks > 0 ? $practicalPassMarks : ($practicalTotal * 0.33);
        
        $theoryPassed = $theoryTotal > 0 ? ($theoryObtained >= $tPass) : true;
        $practicalPassed = $practicalTotal > 0 ? ($practicalObtained >= $pPass) : true;
        
        return $theoryPassed && $practicalPassed;
    }

    /**
     * Check if student passed all subjects for Nepal system
     * 
     * @param int $studentId
     * @param int $classId
     * @param int $academicYearId
     * @return array
     */
    public function checkStudentPassedAllSubjects(
        int $studentId, 
        int $classId, 
        int $academicYearId
    ): array {
        
        $results = Result::with('subject')
            ->where('student_id', $studentId)
            ->where('class_id', $classId)
            ->where('academic_year_id', $academicYearId)
            ->get();

        $passedSubjects = [];
        $failedSubjects = [];
        $allPassed = true;

        foreach ($results as $result) {
            $subject = $result->subject;
            $theoryPassMarks = $subject->theory_pass_marks ?? 33;
            $practicalPassMarks = $subject->practical_pass_marks ?? 33;
            
            $passed = $this->validateNepalPassingCriteria(
                $result->marks_theory,
                $subject->theory_marks ?? 0,
                $result->marks_practical,
                $subject->practical_marks ?? 0,
                $theoryPassMarks,
                $practicalPassMarks
            );

            if ($passed) {
                $passedSubjects[] = $subject->name;
            } else {
                $failedSubjects[] = $subject->name;
                $allPassed = false;
            }
        }

        return [
            'all_passed' => $allPassed,
            'passed_subjects' => $passedSubjects,
            'failed_subjects' => $failedSubjects,
            'total_subjects' => $results->count()
        ];
    }

    /**
     * Check if student passed all subjects for a specific term/exam type
     * 
     * @param int $studentId
     * @param int $classId
     * @param string $examType
     * @param int $academicYearId
     * @return array
     */
    public function checkStudentPassedTermSubjects(
        int $studentId, 
        int $classId, 
        string $examType,
        int $academicYearId
    ): array {
        
        $results = Result::with('subject')
            ->where('student_id', $studentId)
            ->where('class_id', $classId)
            ->where('academic_year_id', $academicYearId)
            ->where('exam_type', $examType)
            ->get();

        $passedSubjects = [];
        $failedSubjects = [];
        $allPassed = true;

        foreach ($results as $result) {
            $subject = $result->subject;
            $theoryPassMarks = (float)($subject->theory_pass_marks ?? 33);
            $practicalPassMarks = (float)($subject->practical_pass_marks ?? 33);
            
            $passed = $this->validateNepalPassingCriteria(
                (float)$result->marks_theory,
                (float)($subject->theory_marks ?? 0),
                (float)$result->marks_practical,
                (float)($subject->practical_marks ?? 0),
                $theoryPassMarks,
                $practicalPassMarks
            );

            if ($passed) {
                $passedSubjects[] = $subject->name;
            } else {
                $failedSubjects[] = $subject->name;
                $allPassed = false;
            }
        }

        return [
            'all_passed' => $allPassed,
            'passed_subjects' => $passedSubjects,
            'failed_subjects' => $failedSubjects,
            'total_subjects' => $results->count()
        ];
    }

    /**
     * Calculate result based on ResultSetting configuration
     * 
     * @param Result $result
     * @param float $theoryObtained
     * @param float $theoryMax
     * @param float $practicalObtained
     * @param float $practicalMax
     * @param ResultSetting $resultSetting
     * @param float $theoryPass|null
     * @param float $practicalPass|null
     * @return array ['gpa' => float, 'percentage' => float|null]
     */
    public function calculateResult(
        Result $result, 
        float $theoryObtained, 
        float $theoryMax,
        float $practicalObtained,
        float $practicalMax,
        ResultSetting $resultSetting,
        ?float $theoryPass = null,
        ?float $practicalPass = null
    ): array
    {
        $totalObtained = $theoryObtained + $practicalObtained;
        $totalMax = $theoryMax + $practicalMax;

        // Check Nepal Passing Criteria
        $isPassed = $this->validateNepalPassingCriteria(
            $theoryObtained,
            $theoryMax,
            $practicalObtained,
            $practicalMax,
            $theoryPass ?? 0,
            $practicalPass ?? 0
        );

        if (!$isPassed) {
            $gpa = 0.0; // NG (Non-Graded)
        } else {
            $gpa = $this->calculateGPA($totalObtained, $totalMax);
        }

        $percentage = null;
        
        // Calculate percentage if result_type is percentage
        if ($resultSetting->result_type === 'percentage') {
            $percentage = $this->calculatePercentage($totalObtained, $totalMax);
        }
        
        return [
            'gpa' => $gpa,
            'percentage' => $percentage,
            'is_passed' => $isPassed
        ];
    }

    /**
     * Calculate weighted final result for a student
     * 
     * @param int $studentId
     * @param int $classId
     * @param ResultSetting $resultSetting
     * @param int|null $academicYearId
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
            $weightedSubjectPractical = 0;
            $weightedSubjectTheory = 0;
            $termWeightSum = 0;
            $subjectName = '';
            $subjectFullTheory = 0;
            $subjectPassTheory = 0;
            $subjectFullPractical = 0;
            $subjectPassPractical = 0;

            foreach ($terms as $term) {
                $result = Result::with('subject.activities')
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
                    $weightedSubjectPractical += ($result->marks_practical * $weight);
                    $weightedSubjectTheory += ($result->marks_theory * $weight);
                    $termWeightSum += $weight;

                    // Set subject constants (theory/practical marks)
                    if ($subjectFullTheory === 0) {
                        $subjectFullTheory = $result->subject->theory_marks ?? 0;
                        $subjectPassTheory = $result->subject->theory_pass_marks ?? 0;
                        $subjectFullPractical = $result->subject->practical_marks ?? 0;
                        $subjectPassPractical = $result->subject->practical_pass_marks ?? 0;
                    }
                }
            }

            if ($termWeightSum > 0) {
                $finalSubjectGPA = round($weightedSubjectGPA / $termWeightSum, 2);
                $finalSubjectPercentage = round($weightedSubjectPercentage / $termWeightSum, 2);
                $finalSubjectPractical = round($weightedSubjectPractical / $termWeightSum, 2);
                $finalSubjectTheory = round($weightedSubjectTheory / $termWeightSum, 2);
                
                $subjectResults[$subjectId] = [
                    'subject_id' => $subjectId,
                    'subject_name' => $subjectName,
                    'weighted_gpa' => $finalSubjectGPA,
                    'weighted_percentage' => $finalSubjectPercentage,
                    'obtained_marks_theory' => $finalSubjectTheory,
                    'full_marks_theory' => $subjectFullTheory,
                    'pass_marks_theory' => $subjectPassTheory,
                    'obtained_marks_practical' => $finalSubjectPractical,
                    'full_marks_practical' => $subjectFullPractical,
                    'pass_marks_practical' => $subjectPassPractical,
                    'grade' => $this->getGradeFromGPA($finalSubjectGPA),
                    'division' => $this->getDivisionFromPercentage($finalSubjectPercentage),
                    'passed_nepal_criteria' => $this->validateNepalPassingCriteria(
                        $finalSubjectTheory,
                        $subjectFullTheory,
                        $finalSubjectPractical,
                        $subjectFullPractical,
                        $subjectPassTheory,
                        $subjectPassPractical
                    )
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

            if ($termResults->isNotEmpty()) {
                $termAvgGPA = $termResults->avg('gpa');
                $termAvgPercentage = $termResults->avg('percentage');
                
                $overallWeightedGPA += ($termAvgGPA * $weight);
                $overallWeightedPercentage += ($termAvgPercentage * $weight);
            }
        }

        $finalGPA = $totalWeight > 0 ? round($overallWeightedGPA / $totalWeight, 2) : 0;
        $finalPercentage = $totalWeight > 0 ? round($overallWeightedPercentage / $totalWeight, 2) : 0;

        // Check Nepal passing criteria for all subjects
        $nepalResult = $this->checkStudentPassedAllSubjects($studentId, $classId, $academicYearId);

        return [
            'final_result' => ($resultSetting->result_type === 'percentage') ? $finalPercentage : $finalGPA,
            'final_gpa' => $finalGPA,
            'final_percentage' => $finalPercentage,
            'final_grade' => $this->getGradeFromGPA($finalGPA),
            'final_division' => $this->getDivisionFromPercentage($finalPercentage),
            'nepal_passed_all_subjects' => $nepalResult['all_passed'],
            'nepal_passed_subjects' => $nepalResult['passed_subjects'],
            'nepal_failed_subjects' => $nepalResult['failed_subjects'],
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