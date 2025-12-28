<?php

namespace Tests\Helpers;

use App\Models\Tenant;
use App\Models\Domain;

trait CreatesTenants
{
    /**
     * Create a tenant with domain.
     */
    protected function createTenant(array $attributes = []): Tenant
    {
        $tenant = Tenant::factory()->create($attributes);
        Domain::factory()->create([
            'domain' => $tenant->domain,
            'tenant_id' => $tenant->id
        ]);
        
        return $tenant;
    }

    /**
     * Create an active tenant.
     */
    protected function createActiveTenant(array $attributes = []): Tenant
    {
        return $this->createTenant(array_merge(['status' => 'active'], $attributes));
    }

    /**
     * Create an inactive tenant.
     */
    protected function createInactiveTenant(array $attributes = []): Tenant
    {
        return $this->createTenant(array_merge(['status' => 'inactive'], $attributes));
    }

    /**
     * Create a suspended tenant.
     */
    protected function createSuspendedTenant(array $attributes = []): Tenant
    {
        return $this->createTenant(array_merge(['status' => 'suspended'], $attributes));
    }

    /**
     * Create multiple tenants for testing.
     */
    protected function createMultipleTenants(int $count = 3): array
    {
        $tenants = [];
        
        for ($i = 0; $i < $count; $i++) {
            $tenants[] = $this->createActiveTenant();
        }
        
        return $tenants;
    }

    /**
     * Create a tenant with specific domain.
     */
    protected function createTenantWithDomain(string $domain, array $attributes = []): Tenant
    {
        $tenant = Tenant::factory()->create(array_merge(['domain' => $domain], $attributes));
        Domain::factory()->create([
            'domain' => $domain,
            'tenant_id' => $tenant->id
        ]);
        
        return $tenant;
    }

    /**
     * Create a tenant with multiple domains.
     */
    protected function createTenantWithMultipleDomains(array $domains, array $attributes = []): Tenant
    {
        $tenant = Tenant::factory()->create($attributes);
        
        foreach ($domains as $domain) {
            Domain::factory()->create([
                'domain' => $domain,
                'tenant_id' => $tenant->id
            ]);
        }
        
        return $tenant;
    }
}