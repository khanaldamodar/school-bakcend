<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class SchoolClass extends Model
{
    protected $table = 'classes'; // optional if you name the model Class

    protected $fillable = [
        'name',
        'class_code',
        'section',
        'class_teacher_id'
    ];

    public function subjects()
    {
        return $this->belongsToMany(Subject::class, 'class_subject', 'class_id', 'subject_id')->withPivot('teacher_id');
    }
    public function classTeacher()
    {
        return $this->belongsTo(Teacher::class, 'class_teacher_id');
    }

    public function subjectsWithTeachers()
    {
        return $this->belongsToMany(Subject::class, 'class_subject_teacher')
            ->withPivot('teacher_id')
            ->withTimestamps();
    }

    public function activities()
    {
        return $this->hasMany(ExtraCurricularActivity::class, 'subject_id');
    }





}
