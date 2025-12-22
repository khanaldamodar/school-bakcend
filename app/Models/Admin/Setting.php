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
        'isWeighted',
        'district',
        'local_body',
        'ward'
    ];

    protected $hidden =[
        "logo_public_id",
        "favicon_public_id"
    ];

    protected static function booted()
{
    static::saved(function ($setting) {
        $tenantId = tenant('id');
        Cache::forget("settings_{$tenantId}");
        Cache::forever("settings_{$tenantId}", $setting);
    });

    static::deleted(function () {
        $tenantId = tenant('id');
        Cache::forget("settings_{$tenantId}");
    });
}

public function resultSetting()
    {
        return $this->hasOne(ResultSetting::class, 'setting_id');
    }

}
