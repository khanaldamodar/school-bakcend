<?php

namespace App\Models\Admin;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    protected static function booted()
    {
        static::addGlobalScope('not_deleted', function ($builder) {
            $builder->where('is_deleted', false);
        });

        static::creating(function ($student) {
            if (!$student->student_id) {
                $setting = Setting::first();
                if ($setting) {
                    $localBody = $setting->local_body ?? 'SB';
                    $words = explode(' ', trim($localBody));
                    if (count($words) >= 2) {
                        $localBodyInitials = strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1));
                    } else {
                        $localBodyInitials = strtoupper(substr($localBody, 0, 2));
                    }
                    
                    $ward = $setting->ward ?? '00';
                    
                    // Format: [2 initials][Ward][4 digit sequence]
                    // Example: KA320001
                    
                    $lastStudent = self::withoutGlobalScopes()
                        ->where('student_id', 'like', "{$localBodyInitials}{$ward}%")
                        ->orderBy('id', 'desc')
                        ->first();

                    if ($lastStudent && preg_match('/(\d{4})$/', $lastStudent->student_id, $matches)) {
                        $nextNumber = intval($matches[1]) + 1;
                    } else {
                        $nextNumber = 1;
                    }

                    $student->student_id = $localBodyInitials . $ward . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
                }
            }
        });
    }

    protected $fillable = [
        'student_id',
        'first_name',
        'middle_name',
        'last_name',
        'dob',
        'gender',
        'email',
        'phone',
        'class_id',
        'enrollment_year',
        'is_transferred',
        'transferred_to',
        'user_id',
        'address',
        'blood_group',
        'is_disabled',
        'is_tribe',
        'image',
        'cloudinary_id',
        "roll_number",
        'is_deleted',
        'ethnicity',
        'disability_options',

    ];
    public function class()
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function histories()
    {
        return $this->hasMany(StudentClassHistory::class);
    }
    public function parents()
    {
        return $this->belongsToMany(ParentModel::class, 'parent_student', 'student_id', 'parent_id')
            ->withTimestamps();
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id'); // <-- optional, if you want to access the login info
    }

    public function results()
    {
        return $this->hasMany(Result::class, 'student_id');
    }

    public function clubs()
    {
        return $this->belongsToMany(Club::class, 'student_club')
            ->withPivot('id', 'position')
            ->withTimestamps();
    }

    public function finalResults()
    {
        return $this->hasMany(FinalResult::class, 'student_id');
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class, 'student_id');
    }

}
