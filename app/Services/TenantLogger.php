<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class TenantLogger
{
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
        self::configureTenantPath('students');
        $context['tenant'] = self::getTenantId();
        Log::channel('students')->{$level}($message, $context);
    }

    /**
     * Log to activities-specific channel
     */
    public static function activities(string $level, string $message, array $context = []): void
    {
        self::configureTenantPath('activities');
        $context['tenant'] = self::getTenantId();
        Log::channel('activities')->{$level}($message, $context);
    }

    /**
     * Log to results-specific channel
     */
    public static function results(string $level, string $message, array $context = []): void
    {
        self::configureTenantPath('results');
        $context['tenant'] = self::getTenantId();
        Log::channel('results')->{$level}($message, $context);
    }

    /**
     * Log to tenant-specific general channel
     */
    public static function tenant(string $level, string $message, array $context = []): void
    {
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
}
