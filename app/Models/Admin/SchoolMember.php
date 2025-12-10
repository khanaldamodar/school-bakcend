<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class SchoolMember extends Model
{

    protected $table = 'school_members';
    protected $fillable = [
        'name',
        'about',
        'image',
        'qualification',
        'address',
        'phone',
        'email',
        'staff_type',
        'staff_subtype',
        'gender',
        'date_of_birth',
        'joining_date',
        'blood_group',
        'nationality',
        'is_disabled',
        'caste',
        'is_active',
        'image_public_id'
    ];

    protected $casts = [
        'is_disabled' => 'boolean',
        'is_active' => 'boolean',
        'date_of_birth' => 'date',
        'joining_date' => 'date',
    ];
}
