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
        'end_time'
    ];

    
}
