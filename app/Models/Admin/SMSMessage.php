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

    protected $appends = [
        'recipient_name',
    ];

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * Get the recipient name based on recipient_model and recipient_id
     */
    public function getRecipientNameAttribute()
    {
        if (!$this->recipient_model || !$this->recipient_id) {
            return null;
        }

        try {
            $model = $this->recipient_model;
            $recipient = $model::find($this->recipient_id);

            if (!$recipient) {
                return null;
            }

            // Handle different model types
            if ($model === Student::class) {
                $nameParts = array_filter([
                    $recipient->first_name,
                    $recipient->middle_name,
                    $recipient->last_name
                ]);
                return implode(' ', $nameParts);
            }

            if ($model === ParentModel::class) {
                $nameParts = array_filter([
                    $recipient->first_name,
                    $recipient->middle_name,
                    $recipient->last_name
                ]);
                return implode(' ', $nameParts);
            }

            if ($model === Teacher::class) {
                return $recipient->name;
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }
}


