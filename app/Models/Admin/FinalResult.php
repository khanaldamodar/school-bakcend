<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class FinalResult extends Model
{
    protected $fillable = [
        'student_id',
        'class_id',
        'academic_year_id',
        'subject_id',
        'final_gpa',
        'final_percentage',
        'final_theory_marks',
        'final_practical_marks',
        'final_grade',
        'final_division',
        'is_passed',
        'result_type',
        'calculation_method',
        'rank',
        'remarks',
        'term_breakdown',
    ];

    protected $casts = [
        'term_breakdown' => 'array',
        'is_passed' => 'boolean',
        'final_gpa' => 'float',
        'final_percentage' => 'float',
        'final_theory_marks' => 'float',
        'final_practical_marks' => 'float',
    ];

    /**
     * Get the student that owns this final result
     */
    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Get the class for this final result
     */
    public function class()
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    /**
     * Get the academic year for this final result
     */
    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /**
     * Get the subject for this final result (null for overall result)
     */
    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * Scope to get overall final results (not subject-specific)
     */
    public function scopeOverall($query)
    {
        return $query->whereNull('subject_id');
    }

    /**
     * Scope to get subject-specific final results
     */
    public function scopeBySubject($query)
    {
        return $query->whereNotNull('subject_id');
    }

    /**
     * Scope to get final results for a specific academic year
     */
    public function scopeForAcademicYear($query, $academicYearId)
    {
        return $query->where('academic_year_id', $academicYearId);
    }

    /**
     * Scope to get final results for a specific class
     */
    public function scopeForClass($query, $classId)
    {
        return $query->where('class_id', $classId);
    }

    /**
     * Scope to get passed students
     */
    public function scopePassed($query)
    {
        return $query->where('is_passed', true);
    }

    /**
     * Scope to get failed students
     */
    public function scopeFailed($query)
    {
        return $query->where('is_passed', false);
    }
}
