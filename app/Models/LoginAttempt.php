<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class LoginAttempt extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'email',
        'ip_address',
        'attempted_at',
    ];

    protected $casts = [
        'attempted_at' => 'datetime',
    ];

    /**
     * Check if an email is currently locked out
     *
     * @param string $email
     * @return bool
     */
    public static function isLockedOut(string $email): bool
    {
        $lockoutMinutes = 10;
        $maxAttempts = 5;
        $attemptWindow = 1; // minute

        // Get all attempts in the last 10 minutes (the lockout period)
        $lockoutThreshold = Carbon::now()->subMinutes($lockoutMinutes);
        
        $attempts = self::where('email', $email)
            ->where('attempted_at', '>=', $lockoutThreshold)
            ->orderBy('attempted_at', 'desc')
            ->get();

        // If we have less than 5 attempts total, no lockout
        if ($attempts->count() < $maxAttempts) {
            return false;
        }

        // Check if any 5 consecutive attempts occurred within 1 minute window
        // We need to find if the 5th attempt is within 1 minute of the 1st attempt
        for ($i = 0; $i <= $attempts->count() - $maxAttempts; $i++) {
            $firstAttempt = $attempts[$i];
            $fifthAttempt = $attempts[$i + 4];
            
            // Calculate time difference between 1st and 5th attempt
            $timeDiff = $firstAttempt->attempted_at->diffInMinutes($fifthAttempt->attempted_at);
            
            // If 5 attempts occurred within 1 minute window
            if ($timeDiff < $attemptWindow) {
                // Check if we're still within 10 minutes from the 5th attempt
                $lockoutEndsAt = $fifthAttempt->attempted_at->copy()->addMinutes($lockoutMinutes);
                
                if (Carbon::now()->lessThan($lockoutEndsAt)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get the time remaining for lockout in seconds
     *
     * @param string $email
     * @return int
     */
    public static function getLockoutTimeRemaining(string $email): int
    {
        $lockoutMinutes = 10;
        $maxAttempts = 5;
        $attemptWindow = 1; // minute

        // Get all attempts in the last 10 minutes
        $lockoutThreshold = Carbon::now()->subMinutes($lockoutMinutes);
        
        $attempts = self::where('email', $email)
            ->where('attempted_at', '>=', $lockoutThreshold)
            ->orderBy('attempted_at', 'desc')
            ->get();

        if ($attempts->count() < $maxAttempts) {
            return 0;
        }

        // Find the earliest lockout trigger (5 attempts within 1 minute)
        for ($i = 0; $i <= $attempts->count() - $maxAttempts; $i++) {
            $firstAttempt = $attempts[$i];
            $fifthAttempt = $attempts[$i + 4];
            
            $timeDiff = $firstAttempt->attempted_at->diffInMinutes($fifthAttempt->attempted_at);
            
            if ($timeDiff < $attemptWindow) {
                $lockoutEndsAt = $fifthAttempt->attempted_at->copy()->addMinutes($lockoutMinutes);
                $secondsRemaining = Carbon::now()->diffInSeconds($lockoutEndsAt, false);
                
                if ($secondsRemaining > 0) {
                    return (int) $secondsRemaining;
                }
            }
        }

        return 0;
    }

    /**
     * Record a login attempt
     *
     * @param string $email
     * @param string $ipAddress
     * @return void
     */
    public static function recordAttempt(string $email, string $ipAddress): void
    {
        self::create([
            'email' => $email,
            'ip_address' => $ipAddress,
            'attempted_at' => Carbon::now(),
        ]);
    }

    /**
     * Clear old login attempts (older than 10 minutes)
     *
     * @param string $email
     * @return void
     */
    public static function clearOldAttempts(string $email): void
    {
        $threshold = Carbon::now()->subMinutes(10);
        
        self::where('email', $email)
            ->where('attempted_at', '<', $threshold)
            ->delete();
    }

    /**
     * Clear all attempts for an email (used after successful login)
     *
     * @param string $email
     * @return void
     */
    public static function clearAttempts(string $email): void
    {
        self::where('email', $email)->delete();
    }
}
