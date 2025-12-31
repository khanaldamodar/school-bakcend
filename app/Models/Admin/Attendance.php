<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'academic_year_id',
        'student_id',
        'teacher_id',
        'class_id',
        'attendance_date',
        'check_in',
        'check_out',
        'status',
        'source',
        'device_id',
        'device_user_id',
        'remarks',
    ];

    protected $casts = [
        'attendance_date' => 'date',
        'check_in' => 'datetime:H:i',
        'check_out' => 'datetime:H:i',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function teacher()
    {
        return $this->belongsTo(Teacher::class);
    }

    public function schoolClass()
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /**
     * Scope a query to only include student attendance.
     */
    public function scopeForStudents($query)
    {
        return $query->whereNotNull('student_id');
    }

    /**
     * Scope a query to only include teacher attendance.
     */
    public function scopeForTeachers($query)
    {
        return $query->whereNotNull('teacher_id');
    }
}
