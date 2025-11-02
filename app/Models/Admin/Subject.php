<?php

namespace App\Models\Admin;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class Subject extends Model
{

    protected $table = 'subjects'; 
    protected $fillable = [
        'name',
        'subject_code',
        'theory_marks',
        'practical_marks',
        'teacher_id',
        'class_id'

    ];

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function classes()
    {
        return $this->belongsToMany(SchoolClass::class, 'class_subject_teacher', 'subject_id', 'class_id')->withPivot('teacher_id');
    }

    public function classesWithTeachers()
{
    return $this->belongsToMany(SchoolClass::class, 'class_subject_teacher')
                ->withPivot('teacher_id')
                ->withTimestamps();
}

}
