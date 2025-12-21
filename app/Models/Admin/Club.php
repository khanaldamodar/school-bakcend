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
}
