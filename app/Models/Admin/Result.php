<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class Result extends Model
{
    protected $fillable = [
        'student_id',
        'class_id',
        'subject_id',
        'teacher_id',
        'marks_theory',
        'marks_practical',
        'gpa',
        'exam_type',
    ];

    // public function student()
    // {
    //     return $this->belongsTo(Student::class);
    // }

    public function class()
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function teacher()
    {
        return $this->belongsTo(Teacher::class);
    }

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }
}
