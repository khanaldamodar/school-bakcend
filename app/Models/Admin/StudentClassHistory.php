<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class StudentClassHistory extends Model
{
    protected $fillable = [
        'student_id',
        'class_id',
        'year',
        'academic_year_id',
        'roll_number',
        'promoted_date',
        'status',
        'remarks'
    ];

    protected $casts = [
        'promoted_date' => 'date',
    ];

    /**
     * Get the student for this history record
     */
    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Get the class for this history record
     */
    public function class()
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    /**
     * Get the academic year for this history record
     */
    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /**
     * Get all results for this student during this class/year
     */
    public function results()
    {
        return Result::where('student_id', $this->student_id)
            ->where('class_id', $this->class_id)
            ->when($this->academic_year_id, function ($query) {
                // If we have academic year, we could filter results by date range
                $academicYear = $this->academicYear;
                if ($academicYear) {
                    $query->whereBetween('created_at', [
                        $academicYear->start_date,
                        $academicYear->end_date
                    ]);
                }
            })
            ->get();
    }

    /**
     * Scope to get active history records
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope to get promoted history records
     */
    public function scopePromoted($query)
    {
        return $query->where('status', 'promoted');
    }

    /**
     * Scope to get history for a specific academic year
     */
    public function scopeForAcademicYear($query, $academicYearId)
    {
        return $query->where('academic_year_id', $academicYearId);
    }
}
