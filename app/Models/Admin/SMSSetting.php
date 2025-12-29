<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class SMSSetting extends Model
{
    protected $table = 'sms_settings';

    protected $fillable = [
        'academic_year_id',
        'event_type',
        'days_before',
        'target_group',
        'is_active',
        'message_template',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }
}


