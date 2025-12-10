<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class Gallery extends Model
{

    protected $table = 'galleries';
    protected $fillable = [
        'title',
        'description',
        'content_type',
        'for',
        'media',
    ];

    protected $casts = [
        'media' => 'array'

    ];
}
