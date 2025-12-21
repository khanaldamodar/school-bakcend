<?php

namespace App\Models\Admin;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class Teacher extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'email',
        'phone',
        'qualification',
        'address',
        'blood_group',
        'is_disabled',
        'is_tribe',
        'image',
        'gender',
        'dob',
        'nationality',
        'cloudinary_id',
        'grade'
    ];

    public function subjects()
    {
        return $this->belongsToMany(Subject::class, 'subject_teacher', 'teacher_id', 'subject_id');
    }

    public function classTeacherOf()
    {
        return $this->belongsTo(SchoolClass::class, 'id', 'class_teacher_id', );
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function subjectClasses()
    {
        return $this->belongsToMany(Subject::class, 'class_subject_teacher')
            ->withPivot('class_id')
            ->withTimestamps();
    }

    public function classSubjects()
    {
        // class_subject_teacher: id, class_id, subject_id, teacher_id
        return $this->belongsToMany(Subject::class, 'class_subject_teacher', 'teacher_id', 'subject_id')
            ->withPivot('class_id')
            ->withTimestamps();
    }

    public function classesTaught()
    {
        return $this->belongsToMany(SchoolClass::class, 'class_subject_teacher', 'teacher_id', 'class_id')
            ->withPivot('subject_id')
            ->withTimestamps();
    }

    // App\Models\Admin\Teacher.php
    public function classes()
    {
        return $this->belongsToMany(SchoolClass::class, 'class_subject_teacher', 'teacher_id', 'class_id')
            ->with('subjects'); // eager load subjects
    }





}
