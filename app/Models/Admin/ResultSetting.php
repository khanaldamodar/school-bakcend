<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class ResultSetting extends Model
{
    protected $fillable = ['setting_id', 'total_terms', 'calculation_method', 'result_type', 'term_weights', 'evaluation_per_term'];

    public function setting()
    {
        return $this->belongsTo(Setting::class, 'setting_id');
    }

    public function terms()
    {
        return $this->hasMany(Term::class);
    }

    protected $casts = [
        'term_weights' => 'array',
        'evaluation_per_term' => 'boolean',
    ];

}
