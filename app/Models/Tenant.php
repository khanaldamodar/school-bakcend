<?php

namespace App\Models;

use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains;


    public static function getCustomColumns(): array
    {
        return [
            'id',
            'name',
            'email',
            'district',
            'local_unit',
            'ward',
            'password',
            'database'
        ];
    }

    public function getPasswordAttribute($value)
    {
        return $this->attributes['password'] = bcrypt($value);
    }

    public function getDomainAttribute()
    {
        return $this->domains()->first()?->domain;
    }
    

    public static function booted()
    {
        static::creating(function (Tenant $tenant) {
            // Example: use domain_name as DB name
            $dbName = strtolower($tenant->domain . '_db');
            $tenant->database = $dbName;
            $tenant->tenancy_db_name = $dbName;
        });
    }

    public function getTenantDatabaseName(): string
    {
        return $this->database; // use your custom DB name
    }

    protected $hidden = [
        'password'
    ];
}