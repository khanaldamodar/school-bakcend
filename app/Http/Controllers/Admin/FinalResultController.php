<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\AcademicYear;
use App\Models\Admin\FinalResult;
use App\Models\Admin\SchoolClass;
use App\Models\Admin\Student;
use App\Models\Admin\Subject;
use App\Services\ResultCalculationService; // reusing if useful, or custom logic
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FinalResultController extends Controller
{
    /**
     * Generate Final Results for a Class (Admin Only)
     */
    public function generate(Request $request, $domain)
    {
        // 1. Authorization: Only Admin can generate
        $user = $request->user();
        if ($user->role !== 'admin' && $user->role !== 'super_admin') {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized. Only Admin can generate final results.',
            ], 403);
        }

        // 2. Validation
        $validated = $request->validate([
            'class_id' => 'required|exists:classes,id',
            'academic_year_id' => 'required|exists:academic_years,id',
            'min_marks' => 'required|numeric|min:0|max:100',
            'max_marks' => 'required|numeric|min:0|max:100|gte:min_marks',
            // Optional: specifically separate GPA range if provided, but user said "Same for GPA"
            // which usually means generate marks that LEAD to similar high GPA.
            // We will calculate GPA from marks.
        ]);

        $classId = $validated['class_id'];
        $academicYearId = $validated['academic_year_id'];
        $min = $validated['min_marks'];
        $max = $validated['max_marks'];

        try {
            DB::beginTransaction();

            // 3. Clear existing FinalResults for this class/year to avoid duplicates?
            // The user implies "Provide Distinction for all", overwriting/creating.
            // Let's delete existing purely generated ones or just update?
            // Safer to delete old ones for this batch context to ensure clean slate if re-run.
            FinalResult::where('class_id', $classId)
                ->where('academic_year_id', $academicYearId)
                ->delete();

            // 4. Fetch Students
            $students = Student::where('class_id', $classId)->get();

            if ($students->isEmpty()) {
                return response()->json([
                    'status' => false,
                    'message' => 'No students found in this class.',
                ], 404);
            }

            // 5. Fetch Subjects for the Class
            // Assuming subjects are linked to class via pivot or direct relation
            // The Subject model has `classes()` relation.
            $class = SchoolClass::find($classId);
            $subjects = $class->subjects; // Assuming relationship exists

            if ($subjects->isEmpty()) {
                // Fallback: search subjects where class_id matches if not using pivot
                $subjects = Subject::where('class_id', $classId)->get();
            }

            if ($subjects->isEmpty()) {
                return response()->json([
                    'status' => false,
                    'message' => 'No subjects assigned to this class.',
                ], 404);
            }

            $generatedCount = 0;

            foreach ($students as $student) {
                $totalMarksObtained = 0;
                $totalFullMarks = 0;
                $totalGPA = 0;
                $subjectCount = 0;

                foreach ($subjects as $subject) {
                    // Generate random marks
                    // Logic: random float between min and max
                    // We need to respect subject full marks.
                    // If distinction (80-100%) is requested, we apply that percentage to the subject's full marks.
                    
                    $fullTheory = $subject->theory_marks ?? 0;
                    $fullPractical = $subject->practical_marks ?? 0;
                    $fullTotal = $fullTheory + $fullPractical;

                    if ($fullTotal == 0) continue;

                    // Generate a random percentage for this specific subject/student
                    // integer rand for diversity
                    $randomPercentage = rand($min, $max); 
                    // Add some decimals for realism
                    $randomPercentage += (rand(0, 99) / 100);
                    
                    // Cap at User Max
                    if ($randomPercentage > $max) $randomPercentage = $max;

                    // Calculate obtained average
                    $obtainedTotal = ($fullTotal * $randomPercentage) / 100;

                    // Split into theory/practical proportionally
                    $obtainedTheory = 0;
                    $obtainedPractical = 0;

                    if ($fullTheory > 0) {
                        $obtainedTheory = ($obtainedTotal * ($fullTheory / $fullTotal));
                    }
                    if ($fullPractical > 0) {
                        $obtainedPractical = ($obtainedTotal * ($fullPractical / $fullTotal));
                    }
                    
                    // Rounding
                    $obtainedTheory = round($obtainedTheory, 2);
                    $obtainedPractical = round($obtainedPractical, 2);
                    
                    // Recalculate exact used percentage/GPA just in case rounding shifted it
                    $actualObtained = $obtainedTheory + $obtainedPractical;
                    $actualPercentage = ($actualObtained / $fullTotal) * 100;

                    // Calculate Subject GPA (Standard 4.0 scale approximation)
                    // >= 90 -> 4.0, 80-90 -> 3.6, etc.
                    // Or simple calculation: (Percentage / 25) ? No, standard grading differs.
                    // I'll implement a simple mapper or use user's "Same for GPA" instruction:
                    // "generate random number between 80-100... Same for GPA"
                    // If user meant "Generate random GPA between X and Y", I need that range.
                    // But if they provided 80-100 (marks range), I'll infer GPA from marks.
                    
                    $subjectGPA = $this->calculateGPA($actualPercentage);

                    // Create FinalResult Entry for Subject
                    FinalResult::create([
                        'student_id' => $student->id,
                        'class_id' => $classId,
                        'academic_year_id' => $academicYearId,
                        'subject_id' => $subject->id,
                        'final_theory_marks' => $obtainedTheory,
                        'final_practical_marks' => $obtainedPractical,
                        'final_percentage' => $actualPercentage,
                        'final_gpa' => $subjectGPA, // Subject GPA
                        'is_passed' => true, // Assuming distinction/high marks pass
                        'result_type' => 'percentage', // Must be 'gpa' or 'percentage'
                        'calculation_method' => 'simple', // It's a direct generation, not weighted from terms
                    ]);

                    $totalMarksObtained += $actualObtained;
                    $totalFullMarks += $fullTotal;
                    $totalGPA += $subjectGPA; // Summing for average later? 
                    // Weighted GPA is better: (GPA * CreditHour) / TotalCreditHours. 
                    // Assuming all subjects equal weight for now unless credit hours exist.
                    $subjectCount++;
                }

                // Create Overall Result (Subject ID null)
                if ($subjectCount > 0) {
                    $overallPercentage = ($totalMarksObtained / $totalFullMarks) * 100;
                    $overallGPA = $this->calculateGPA($overallPercentage); // Or average of subject GPAs

                    FinalResult::create([
                        'student_id' => $student->id,
                        'class_id' => $classId,
                        'academic_year_id' => $academicYearId,
                        'subject_id' => null, // Overall
                        'final_theory_marks' => null,
                        'final_practical_marks' => null, // these are aggregate, maybe not needed or sum
                        'final_percentage' => $overallPercentage,
                        'final_gpa' => $overallGPA,
                        'is_passed' => true,
                        'result_type' => 'percentage', // Must be 'gpa' or 'percentage'
                        'calculation_method' => 'simple',
                        'remarks' => $this->getRemarks($overallGPA) . " (Generated)"
                    ]);
                }
                
                $generatedCount++;
            }

            // 6. Calculate Ranks
            $overallResults = FinalResult::where('class_id', $classId)
                ->where('academic_year_id', $academicYearId)
                ->whereNull('subject_id')
                ->orderByDesc('final_gpa')
                ->orderByDesc('final_percentage')
                ->get();

            $rank = 1;
            foreach ($overallResults as $res) {
                // Handle ties? Simple ranking for now (1, 2, 3...)
                $res->update(['rank' => $rank]);
                $rank++;
            }

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => "Successfully generated results for $generatedCount students.",
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => 'Error generating results: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get Final Results formatted for GradeSheet (View Only for non-admins)
     */
    public function getResults(Request $request, $domain, $classId)
    {
        $user = $request->user();
        
        // Authorization check for viewing
        // Admin, Teacher (all or class teacher), Student (own), Parent (own)
        
        // Basic filtering query
        $query = FinalResult::with(['student', 'subject'])
            ->where('class_id', $classId);

        $academicYearId = $request->query('academic_year_id');
        if ($academicYearId) {
            $query->where('academic_year_id', $academicYearId);
        }

        // Apply Role Restrictions
        if ($user->role === 'student') {
            $query->where('student_id', $user->student->id ?? 0);
        } elseif ($user->role === 'parent') {
            $childIds = $user->parent->students()->pluck('id');
            $query->whereIn('student_id', $childIds);
        } elseif ($user->role === 'teacher') {
             // Logic for teacher (class teacher or subject teacher) can be complex.
             // For simplicity, allowing teachers to view class results if they teach there?
             // Or restricting. User said "All Other can only View".
             // We'll proceed with basic teacher view (maybe filtered later if needed).
        }
        
        $results = $query->get();

        // Format for GradeSheet
        // We want: Student -> [Subjects...] -> GPA/Marks
        
        $grouped = $results->groupBy('student_id');
        $formatted = [];

        foreach ($grouped as $studentId => $studentResults) {
            $student = $studentResults->first()->student;
            if (!$student) continue;

            $subjectResults = $studentResults->whereNotNull('subject_id')->values();
            $overallResult = $studentResults->whereNull('subject_id')->first();

            $formatted[] = [
                'student' => [
                    'id' => $student->id,
                    'name' => $student->first_name . ' ' . $student->last_name,
                    'roll_number' => $student->roll_number,
                ],
                'subjects' => $subjectResults->map(function($res) {
                    return [
                        'subject_name' => $res->subject->name ?? 'Unknown',
                        'obtained_marks' => ($res->final_theory_marks + $res->final_practical_marks),
                        'percentage' => $res->final_percentage,
                        'gpa' => $res->final_gpa,
                        'grade' => $res->final_grade // if we stored it, else calculate
                    ];
                }),
                'overall' => [
                    'gpa' => $overallResult ? $overallResult->final_gpa : null,
                    'percentage' => $overallResult ? $overallResult->final_percentage : null,
                    'rank' => $overallResult ? $overallResult->rank : null,
                ]
            ];
        }

        return response()->json([
            'status' => true,
            'data' => $formatted
        ]);
    }

    private function calculateGPA($percentage) {
        if ($percentage >= 90) return 4.0;
        if ($percentage >= 80) return 3.6;
        if ($percentage >= 70) return 3.2;
        if ($percentage >= 60) return 2.8;
        if ($percentage >= 50) return 2.4;
        if ($percentage >= 40) return 2.0;
        return 1.6; // Fail or low
    }
    
    private function getRemarks($gpa) {
        if ($gpa >= 3.6) return 'Outstanding';
        if ($gpa >= 3.2) return 'Excellent';
        if ($gpa >= 2.8) return 'Very Good';
        if ($gpa >= 2.4) return 'Good';
        return 'Satisfactory';
    }
}
