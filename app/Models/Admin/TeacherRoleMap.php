<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Relations\Pivot;
 
class TeacherRoleMap extends Pivot
{
    public $incrementing = true;
    protected $table = 'teacher_role_map';

    protected $fillable = [
        'teacher_id',
        'teacher_role_id',
        'start_date',
        'end_date',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
    ];
}
