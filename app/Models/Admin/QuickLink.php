<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class QuickLink extends Model
{
    protected $fillable = [
        'title',
        'url'
    ];
}
