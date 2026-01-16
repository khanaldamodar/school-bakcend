<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class ClassTeacherHistory extends Model
{
    protected $fillable = [
        'class_id',
        'teacher_id',
        'academic_year_id',
        'start_date',
        'end_date',
        'is_active',
    ];

    public function schoolClass()
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function teacher()
    {
        return $this->belongsTo(Teacher::class, 'teacher_id');
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class, 'academic_year_id');
    }
}
