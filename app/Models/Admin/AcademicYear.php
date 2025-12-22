<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class AcademicYear extends Model
{
    protected $fillable = [
        'name',
        'start_date',
        'end_date',
        'is_current',
        'description'
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_current' => 'boolean',
    ];

    /**
     * Get the current academic year
     */
    public static function current()
    {
        return static::where('is_current', true)->first();
    }

    /**
     * Set this as the current academic year
     */
    public function setCurrent()
    {
        // Set all others to not current
        static::where('id', '!=', $this->id)->update(['is_current' => false]);
        
        // Set this one as current
        $this->update(['is_current' => true]);
    }

    /**
     * Get all student histories for this academic year
     */
    public function studentHistories()
    {
        return $this->hasMany(StudentClassHistory::class);
    }

    /**
     * Check if this academic year is active (current date is within range)
     */
    public function isActive()
    {
        $now = now();
        return $now->between($this->start_date, $this->end_date);
    }
}
