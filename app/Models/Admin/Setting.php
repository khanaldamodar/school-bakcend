<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = [
        'name',
        'about',
        'logo',
        'favicon',
        'address',
        'phone',
        'email',
        'facebook',
        'twitter',
        'start_time',
        'end_time',
        'school_type',
        'established_date',
        'principle',
        'favicon_public_id',
        'logo_public_id',
        'number_of_exams',
        'isWeighted'
    ];

    protected $hidden =[
        "logo_public_id",
        "favicon_public_id"
    ];

    protected static function booted()
{
    static::saved(function ($setting) {
        Cache::forget('settings');
        Cache::forever('settings', $setting);
    });

    static::deleted(function () {
        Cache::forget('settings');
    });
}

public function resultSetting()
    {
        return $this->hasOne(ResultSetting::class, 'setting_id');
    }

}
