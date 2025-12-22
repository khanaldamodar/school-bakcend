<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class TeacherRole extends Model
{
    protected $fillable = [
        'role_name',
    ];

    public function teachers()
{
    return $this->belongsToMany(
        Teacher::class,
        'teacher_role_map',
        'teacher_role_id',
        'teacher_id'
    );
}

}
