<?php

namespace App\Models;

use App\Models\Gov\Government;
use Illuminate\Database\Eloquent\Model;

class LocalBody extends Model
{
    protected $table = 'local_bodies';
    protected $fillable = [
        'district',
        'local_unit',
        'local_unit_np',
    ];

    public function governments()
    {
        return $this->hasMany(Government::class, 'local_body_id');
    }
}
