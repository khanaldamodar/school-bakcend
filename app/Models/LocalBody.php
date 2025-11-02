<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LocalBody extends Model
{
    protected $table = 'local_bodies';
    protected $fillable = [
        'district',
        'local_unit',
        'local_unit_np',
    ];
}
