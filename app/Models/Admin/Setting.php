<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'name',
        'about',
        'logo',
        'favicon',
        'address',
        'phone',
        'email',
        'facebook',
        'twitter',
        'start_time',
        'end_time',
        'school_type',
        'established_date',
        'principle',
        'favicon_public_id',
        'logo_public_id'
    ];
}
