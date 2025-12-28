<?php

namespace Database\Factories\Tenant;

use App\Models\Admin\Result;
use App\Models\Admin\Student;
use App\Models\Admin\Subject;
use App\Models\Admin\Term;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Admin\Result>
 */
class ResultFactory extends Factory
{
    protected $model = Result::class;

    public function definition(): array
    {
        // Generate realistic marks for Nepali education system
        $theoryMarks = $this->faker->numberBetween(0, 100);
        $practicalMarks = $this->faker->numberBetween(0, 50);
        $totalMarks = $theoryMarks + $practicalMarks;
        
        return [
            'student_id' => Student::factory(),
            'subject_id' => Subject::factory(),
            'term_id' => Term::factory(),
            'theory_marks' => $theoryMarks,
            'practical_marks' => $practicalMarks,
            'total_marks' => $totalMarks,
            'theory_full_marks' => 100,
            'practical_full_marks' => 50,
            'total_full_marks' => 150,
            'theory_pass_marks' => 33,
            'practical_pass_marks' => 17,
            'total_pass_marks' => 50,
            'grade_point' => $this->calculateGradePoint($totalMarks, 150),
            'grade' => $this->calculateGrade($totalMarks, 150),
            'remarks' => $this->generateRemarks($totalMarks, 150),
            'status' => 'published',
            'academic_year_id' => 1,
            'exam_date' => $this->faker->dateTimeBetween('-6 months', 'now'),
        ];
    }

    /**
     * Calculate grade point based on Nepal education system (4.0 scale).
     */
    private function calculateGradePoint($obtainedMarks, $fullMarks): float
    {
        $percentage = ($obtainedMarks / $fullMarks) * 100;
        
        if ($percentage >= 90) return 4.0;
        if ($percentage >= 80) return 3.6;
        if ($percentage >= 70) return 3.2;
        if ($percentage >= 60) return 2.8;
        if ($percentage >= 50) return 2.4;
        if ($percentage >= 40) return 2.0;
        if ($percentage >= 30) return 1.6;
        if ($percentage >= 20) return 1.2;
        return 0.8;
    }

    /**
     * Calculate grade based on percentage.
     */
    private function calculateGrade($obtainedMarks, $fullMarks): string
    {
        $percentage = ($obtainedMarks / $fullMarks) * 100;
        
        if ($percentage >= 90) return 'A+';
        if ($percentage >= 80) return 'A';
        if ($percentage >= 70) return 'B+';
        if ($percentage >= 60) return 'B';
        if ($percentage >= 50) return 'C+';
        if ($percentage >= 40) return 'C';
        if ($percentage >= 30) return 'D+';
        if ($percentage >= 20) return 'D';
        return 'E';
    }

    /**
     * Generate remarks based on performance.
     */
    private function generateRemarks($obtainedMarks, $fullMarks): string
    {
        $percentage = ($obtainedMarks / $fullMarks) * 100;
        
        if ($percentage >= 90) return 'Outstanding';
        if ($percentage >= 80) return 'Excellent';
        if ($percentage >= 70) return 'Very Good';
        if ($percentage >= 60) return 'Good';
        if ($percentage >= 50) return 'Satisfactory';
        if ($percentage >= 40) return 'Average';
        if ($percentage >= 30) return 'Below Average';
        return 'Needs Improvement';
    }

    /**
     * Create a result with excellent performance.
     */
    public function excellent(): static
    {
        return $this->state(fn () => [
            'theory_marks' => $this->faker->numberBetween(85, 100),
            'practical_marks' => $this->faker->numberBetween(40, 50),
        ]);
    }

    /**
     * Create a result with failing marks.
     */
    public function failed(): static
    {
        return $this->state(fn () => [
            'theory_marks' => $this->faker->numberBetween(0, 30),
            'practical_marks' => $this->faker->numberBetween(0, 15),
        ]);
    }

    /**
     * Create a result with average performance.
     */
    public function average(): static
    {
        return $this->state(fn () => [
            'theory_marks' => $this->faker->numberBetween(50, 70),
            'practical_marks' => $this->faker->numberBetween(25, 35),
        ]);
    }
}