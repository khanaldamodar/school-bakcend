<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class WebsiteSetting extends Model
{
    protected $table = 'wesbite_settings';
    protected $fillable = [
        'hero_title',
        'hero_desc',
        'hero_image',
        'heroButtonText',
        'heroButtonUrl',
        'number_of_teachers',
        'number_of_students',
        'year_of_experience',
        'number_of_events',
        'total_awards',
        'total_courses',
        'mission',
        'vision',
        'pass_rate',
        'top_score',
        'history',
        'principal_name',
        'principal_image',
        'principal_message',
        'map_url'
    ];
}
