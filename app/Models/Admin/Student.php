<?php

namespace App\Models\Admin;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    protected $fillable = [
        'first_name',
        'middle_name',
        'last_name',
        'dob',
        'gender',
        'email',
        'phone',
        'class_id',
        'enrollment_year',
        'is_transferred',
        'transferred_to',
        'user_id'
    ];
    public function class()
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function histories()
    {
        return $this->hasMany(StudentClassHistory::class);
    }
    public function parents()
    {
        return $this->belongsToMany(ParentModel::class, 'parent_student', 'student_id', 'parent_id')
            ->withTimestamps();
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id'); // <-- optional, if you want to access the login info
    }

    public function results()
    {
        return $this->hasMany(Result::class, 'student_id');
    }

}
