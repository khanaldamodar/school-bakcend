<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class ResultSetting extends Model
{
    protected $fillable = [
        'setting_id', 
        'academic_year_id', 
        'total_terms', 
        'calculation_method', 
        'result_type', 
        'term_weights', 
        'evaluation_per_term'
    ];

    public function setting()
    {
        return $this->belongsTo(Setting::class, 'setting_id');
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class, 'academic_year_id');
    }

    public function terms()
    {
        return $this->hasMany(Term::class);
    }

    protected $casts = [
        'term_weights' => 'array',
        'evaluation_per_term' => 'boolean',
    ];

    /**
     * Get result settings for a specific academic year
     */
    public static function forAcademicYear($academicYearId)
    {
        return static::with(['terms', 'academicYear'])
            ->where('academic_year_id', $academicYearId)
            ->first();
    }

    /**
     * Get current academic year result settings
     */
    public static function current()
    {
        $currentAcademicYear = \App\Models\Admin\AcademicYear::where('is_current', true)->first();
        if ($currentAcademicYear) {
            return static::forAcademicYear($currentAcademicYear->id);
        }
        return null;
    }

}
