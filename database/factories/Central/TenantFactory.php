<?php

namespace Database\Factories\Central;

use App\Models\Tenant;
use App\Models\Domain;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Tenant>
 */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        $schoolName = $this->faker->company() . ' School';
        $domain = $this->faker->unique()->domainName();
        
        return [
            'name' => $schoolName,
            'domain' => $domain,
            'database' => "{$schoolName}_{$domain}_db",
            'email' => $this->faker->companyEmail(),
            'phone' => $this->faker->phoneNumber(),
            'address' => $this->faker->address(),
            'principal_name' => $this->faker->name(),
            'principal_phone' => $this->faker->phoneNumber(),
            'established_year' => $this->faker->year('-10 years'),
            'school_code' => strtoupper($this->faker->lexify('??????')),
            'status' => 'active',
            'sms_balance' => $this->faker->numberBetween(0, 1000),
            'latitude' => $this->faker->latitude(26.0, 30.0), // Nepal coordinates
            'longitude' => $this->faker->longitude(80.0, 88.0), // Nepal coordinates
            'local_body_id' => $this->faker->numberBetween(1, 100),
            'ward_number' => $this->faker->numberBetween(1, 15),
            'government_id' => $this->faker->numberBetween(1, 10),
        ];
    }

    /**
     * Create a tenant with associated domain.
     */
    public function withDomain(): static
    {
        return $this->afterCreating(function (Tenant $tenant) {
            Domain::factory()->create([
                'domain' => $tenant->domain,
                'tenant_id' => $tenant->id,
            ]);
        });
    }

    /**
     * Create an inactive tenant.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'inactive',
        ]);
    }

    /**
     * Create a suspended tenant.
     */
    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'suspended',
        ]);
    }
}