<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoginHistory extends Model
{
    public $timestamps = false;

    protected $table = 'login_history';

    protected $fillable = [
        'user_id',
        'action',
        'ip_address',
        'user_agent',
        'logged_in_at',
        'logged_out_at',
        'session_duration',
    ];

    protected $casts = [
        'logged_in_at' => 'datetime',
        'logged_out_at' => 'datetime',
    ];

    /**
     * Get the user that owns the login history
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Record a login event
     *
     * @param int $userId
     * @param string $ipAddress
     * @param string|null $userAgent
     * @return LoginHistory
     */
    public static function recordLogin(int $userId, string $ipAddress, ?string $userAgent = null): LoginHistory
    {
        return self::create([
            'user_id' => $userId,
            'action' => 'login',
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'logged_in_at' => now(),
        ]);
    }

    /**
     * Record a logout event by updating the most recent login record
     *
     * @param int $userId
     * @return void
     */
    public static function recordLogout(int $userId): void
    {
        // Find the most recent login record without a logout time
        $loginRecord = self::where('user_id', $userId)
            ->where('action', 'login')
            ->whereNull('logged_out_at')
            ->orderBy('logged_in_at', 'desc')
            ->first();

        if ($loginRecord) {
            $loggedOutAt = now();
            $sessionDuration = $loginRecord->logged_in_at->diffInSeconds($loggedOutAt);

            $loginRecord->update([
                'logged_out_at' => $loggedOutAt,
                'session_duration' => $sessionDuration,
            ]);
        }
    }
}
