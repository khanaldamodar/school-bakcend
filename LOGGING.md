# Tenant-Specific Logging System

## Overview

This application now has a comprehensive logging system that separates logs by:

-   **Tenant (School)**: Each school gets its own log directory
-   **Feature**: Different features (students, activities, results) have separate log files

## Log File Structure

```
storage/logs/
├── laravel.log                          # Central/general logs
└── tenants/
    ├── shaktatech/                      # School 1 logs
    │   ├── laravel-2024-12-20.log      # General logs for this school
    │   ├── students-2024-12-20.log     # Student-specific logs
    │   ├── activities-2024-12-20.log   # Activity-specific logs
    │   └── results-2024-12-20.log      # Results-specific logs
    └── anotherSchool/                   # School 2 logs
        ├── laravel-2024-12-20.log
        ├── students-2024-12-20.log
        └── ...
```

## How to Use

### In Controllers

Instead of using `Log::info()` directly, use the `TenantLogger` service:

```php
use App\Services\TenantLogger;

// For student-related operations
TenantLogger::studentInfo('Student created', ['student_id' => $student->id]);
TenantLogger::studentError('Failed to create student', ['error' => $e->getMessage()]);
TenantLogger::studentWarning('Duplicate student detected', ['email' => $email]);

// For activity-related operations
TenantLogger::activityInfo('Activity created', ['activity_id' => $activity->id]);
TenantLogger::activityError('Failed to create activity', ['error' => $e->getMessage()]);

// For result-related operations
TenantLogger::resultInfo('Result calculated', ['student_id' => $id]);
TenantLogger::resultError('Result calculation failed', ['error' => $e->getMessage()]);

// For general tenant operations
TenantLogger::tenant('info', 'General operation', ['data' => $data]);
```

### Available Methods

**Student Logging:**

-   `TenantLogger::studentInfo($message, $context)`
-   `TenantLogger::studentError($message, $context)`
-   `TenantLogger::studentWarning($message, $context)`

**Activity Logging:**

-   `TenantLogger::activityInfo($message, $context)`
-   `TenantLogger::activityError($message, $context)`

**Result Logging:**

-   `TenantLogger::resultInfo($message, $context)`
-   `TenantLogger::resultError($message, $context)`

**Generic Methods:**

-   `TenantLogger::students($level, $message, $context)` - $level can be 'info', 'error', 'warning', 'debug'
-   `TenantLogger::activities($level, $message, $context)`
-   `TenantLogger::results($level, $message, $context)`
-   `TenantLogger::tenant($level, $message, $context)`

## Viewing Logs

### On Windows (PowerShell)

```powershell
# View latest entries from a specific log
Get-Content storage\logs\tenants\shaktatech\students-2024-12-20.log -Tail 50

# Monitor logs in real-time
Get-Content storage\logs\tenants\shaktatech\students-2024-12-20.log -Wait -Tail 20
```

### On Linux/Mac

```bash
# View latest entries
tail -f storage/logs/tenants/shaktatech/students-2024-12-20.log

# Search for errors
grep "ERROR" storage/logs/tenants/shaktatech/students-*.log
```

## Benefits

1. **Easy Debugging**: Quickly find issues for a specific school without searching through all logs
2. **Feature Isolation**: Debug student issues without wading through activity or result logs
3. **Automatic Organization**: Logs are automatically organized by date and tenant
4. **Production Ready**: Logs rotate daily and are kept for 14 days (configurable in `config/logging.php`)

## Configuration

Edit `config/logging.php` to:

-   Change log retention days: `'days' => env('LOG_DAILY_DAYS', 14)`
-   Change log level: `'level' => env('LOG_LEVEL', 'debug')`
-   Add new feature-specific channels

## Adding New Feature Logs

1. Add a new channel in `config/logging.php`:

```php
'teachers' => [
    'driver' => 'daily',
    'path' => storage_path('logs/tenants/{tenant}/teachers.log'),
    'level' => env('LOG_LEVEL', 'debug'),
    'days' => env('LOG_DAILY_DAYS', 14),
    'replace_placeholders' => true,
],
```

2. Add methods to `TenantLogger` service:

```php
public static function teacherInfo(string $message, array $context = []): void
{
    self::teachers('info', $message, $context);
}

public static function teacherError(string $message, array $context = []): void
{
    self::teachers('error', $message, $context);
}
```

3. Use in your controller:

```php
TenantLogger::teacherInfo('Teacher created', ['teacher_id' => $teacher->id]);
```
