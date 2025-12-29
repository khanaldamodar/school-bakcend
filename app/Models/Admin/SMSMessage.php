<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class SMSMessage extends Model
{
    protected $table = 'sms_messages';

    protected $fillable = [
        'academic_year_id',
        'event_id',
        'sender_id',
        'sender_role',
        'target_group',
        'recipient_phone',
        'recipient_model',
        'recipient_id',
        'message',
        'status',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }
}


