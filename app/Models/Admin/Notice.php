<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class Notice extends Model
{
    protected $fillable =[
        'title',
        'description',
        'notice_date',
        'image',
        'cloudinary_id',
    ];
}
