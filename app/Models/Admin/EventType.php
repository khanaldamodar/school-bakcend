<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class EventType extends Model
{
    protected $fillable = [
        'name',
        'color_code'
    ];



    public function events()
    {
        return $this->hasMany(Event::class);
    }
}
