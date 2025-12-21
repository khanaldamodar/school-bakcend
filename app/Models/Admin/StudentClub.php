<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class StudentClub extends Model
{

    protected $table = 'student_club';

    protected $fillable = [
        'student_id',
        'club_id',
        'position'
    ];

     public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function club()
    {
        return $this->belongsTo(Club::class);
    }
}
