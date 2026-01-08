<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    protected $fillable = [
        'event_type_id',
        'title',
        'date',
        'time',
        'type',
        'description',
        'location'
    ];

    public function eventType()
    {
        return $this->belongsTo(EventType::class);
    }

}
