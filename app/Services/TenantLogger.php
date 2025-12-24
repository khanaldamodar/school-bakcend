<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use App\Models\SystemLog;
use Illuminate\Support\Facades\Auth;

class TenantLogger
{
    /**
     * Write log to database
     */
    protected static function writeToDatabase(string $channel, string $level, string $message, array $context = []): void
    {
        try {
            SystemLog::create([
                'tenant_id' => self::getTenantId(),
                'channel' => $channel,
                'level' => $level,
                'message' => $message,
                'context' => $context,
                'user_id' => Auth::id(),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        } catch (\Exception $e) {
            // Fallback to laravel log if database logging fails to prevent infinite loops
            Log::channel('single')->error("Failed to write system log to database: " . $e->getMessage());
        }
    }

    /**
     * Get the current tenant identifier for logging
     */
    protected static function getTenantId(): string
    {
        try {
            if (tenancy()->initialized) {
                return tenant()->id ?? 'unknown';
            }
        } catch (\Exception $e) {
            // Tenancy not initialized
        }
        return 'central';
    }

    /**
     * Replace {tenant} placeholder in log path
     */
    protected static function configureTenantPath(string $channel): void
    {
        $tenantId = self::getTenantId();
        $config = config("logging.channels.{$channel}");
        
        if (isset($config['path'])) {
            $path = str_replace('{tenant}', $tenantId, $config['path']);
            
            // Ensure directory exists
            $directory = dirname($path);
            if (!file_exists($directory)) {
                mkdir($directory, 0755, true);
            }
            
            config(["logging.channels.{$channel}.path" => $path]);
        }
    }

    /**
     * Log to student-specific channel
     */
    public static function students(string $level, string $message, array $context = []): void
    {
        self::writeToDatabase('students', $level, $message, $context);
        self::configureTenantPath('students');
        $context['tenant'] = self::getTenantId();
        Log::channel('students')->{$level}($message, $context);
    }

    /**
     * Log to activities-specific channel
     */
    public static function activities(string $level, string $message, array $context = []): void
    {
        self::writeToDatabase('activities', $level, $message, $context);
        self::configureTenantPath('activities');
        $context['tenant'] = self::getTenantId();
        Log::channel('activities')->{$level}($message, $context);
    }

    /**
     * Log to results-specific channel
     */
    public static function results(string $level, string $message, array $context = []): void
    {
        self::writeToDatabase('results', $level, $message, $context);
        self::configureTenantPath('results');
        $context['tenant'] = self::getTenantId();
        Log::channel('results')->{$level}($message, $context);
    }

    /**
     * Log to tenant-specific general channel
     */
    public static function tenant(string $level, string $message, array $context = []): void
    {
        self::writeToDatabase('tenant', $level, $message, $context);
        self::configureTenantPath('tenant_daily');
        $context['tenant'] = self::getTenantId();
        Log::channel('tenant_daily')->{$level}($message, $context);
    }

    // Convenience methods for students
    public static function studentInfo(string $message, array $context = []): void
    {
        self::students('info', $message, $context);
    }

    public static function studentError(string $message, array $context = []): void
    {
        self::students('error', $message, $context);
    }

    public static function studentWarning(string $message, array $context = []): void
    {
        self::students('warning', $message, $context);
    }

    // Convenience methods for activities
    public static function activityInfo(string $message, array $context = []): void
    {
        self::activities('info', $message, $context);
    }

    public static function activityError(string $message, array $context = []): void
    {
        self::activities('error', $message, $context);
    }

    // Convenience methods for results
    public static function resultInfo(string $message, array $context = []): void
    {
        self::results('info', $message, $context);
    }

    public static function resultError(string $message, array $context = []): void
    {
        self::results('error', $message, $context);
    }

    /**
     * Semantic CRUD Logging
     */
    public static function logCreate(string $entity, string $message, array $context = []): void
    {
        self::tenant('info', $message, array_merge($context, ['entity' => $entity, 'operation' => 'create']));
    }

    public static function logUpdate(string $entity, string $message, array $context = []): void
    {
        self::tenant('info', $message, array_merge($context, ['entity' => $entity, 'operation' => 'update']));
    }

    public static function logDelete(string $entity, string $message, array $context = []): void
    {
        self::tenant('warning', $message, array_merge($context, ['entity' => $entity, 'operation' => 'delete']));
    }

    public static function logAuth(string $message, array $context = []): void
    {
        self::tenant('info', $message, array_merge($context, ['category' => 'auth']));
    }
}
