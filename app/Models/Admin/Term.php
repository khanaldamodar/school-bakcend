<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class Term extends Model
{
    protected $fillable = [
        'result_setting_id',
        'name',
        'weight',
        'exam_date',
        'publish_date',
        'start_date',
        'end_date'
    ];

    public function resultSetting()
    {
        return $this->belongsTo(ResultSetting::class, 'result_setting_id');
    }


}
