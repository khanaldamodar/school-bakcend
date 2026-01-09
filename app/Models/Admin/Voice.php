<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class Voice extends Model
{
    protected $fillable = [
        'name',
        'email',
        'message',
        'phone',
        'role',
        'photo',
        'cloudinary_id',
        
    ];
}
