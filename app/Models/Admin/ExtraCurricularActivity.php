<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class ExtraCurricularActivity extends Model
{
    protected $fillable = [
        'subject_id',
        'activity_name',
        'full_marks',
        'pass_marks',
    ];

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

}
