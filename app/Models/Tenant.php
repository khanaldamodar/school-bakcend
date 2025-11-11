<?php

namespace App\Models;

use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains;

    protected $hidden = ['password'];

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
            'database',
            'domain'
        ];
    }

    public function setPasswordAttribute($value)
    {
        $this->attributes['password'] = bcrypt($value);
    }

    public function getDomainAttribute()
    {
        return $this->domains()->first()?->domain;
    }

    // ✅ This will run BEFORE Stancl creates the DB
protected static function booted()
{
    static::creating(function ($tenant) {
        // Get the domain from the relationship if not directly set
        $domain = $tenant->domain ?? $tenant->getDomainAttribute() ?? 'domain';
        
        // Replace spaces, dots, hyphens in the school name with underscores
        $schoolNamePart = strtolower(str_replace([' ', '.', '-'], '_', $tenant->name));

        // Replace dots and hyphens in the domain with underscores
        $domainPart = strtolower(str_replace(['.', '-'], '_', $domain));

        // Combine school name and domain to create the database name
        $tenant->database = "{$schoolNamePart}_{$domainPart}_db";
    });
}




    public function getTenantDatabaseName(): string
    {
        return $this->database;
    }
}
