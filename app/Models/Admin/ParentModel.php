<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;

class ParentModel extends Model
{
    protected $table = 'parents';

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'relation'
    ];

    public function students()
    {
        return $this->belongsToMany(Student::class, 'parent_student', 'parent_id', 'student_id')
            ->withTimestamps();
    }
}
