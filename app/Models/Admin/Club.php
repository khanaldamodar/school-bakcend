<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class Club extends Model
{
    protected $fillable = [
        'name',
        'logo',
        'description'
    ];

    public function students()
    {
        return $this->belongsToMany(Student::class, 'student_club')
            ->withPivot('id', 'position')
            ->withTimestamps();
    }

}
