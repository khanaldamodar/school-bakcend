# Test Logging System

## 1. Check if table exists and is empty

php artisan tinker

> > > \App\Models\SystemLog::count()
> > > exit

## 2. Manually create a test log entry

php artisan tinker

> > > \App\Services\TenantLogger::logAuth('Manual test log entry', ['test' => true])
> > > \App\Models\SystemLog::latest()->first()
> > > exit

## 3. View all logs with details

php artisan tinker

> > > \App\Models\SystemLog::select('id', 'channel', 'level', 'message', 'created_at')->latest()->take(10)->get()
> > > exit

## 4. Filter logs by channel

php artisan tinker

> > > \App\Models\SystemLog::where('channel', 'tenant')->get()
> > > exit

## 5. View context data

php artisan tinker

> > > $log = \App\Models\SystemLog::latest()->first()
>>> $log->context
> > > exit
