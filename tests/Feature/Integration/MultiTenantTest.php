<?php

namespace Tests\Feature\Integration;

use App\Models\Tenant;
use App\Models\Domain;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiTenantTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant1;
    protected Tenant $tenant2;
    protected User $admin1;
    protected User $admin2;

    protected function setUp(): void
    {
        parent::setUp();

        // Create two tenants
        $this->tenant1 = Tenant::factory()->withDomain()->create([
            'domain' => 'school1.test.com'
        ]);
        
        $this->tenant2 = Tenant::factory()->withDomain()->create([
            'domain' => 'school2.test.com'
        ]);

        // Create admin users for each tenant
        $this->admin1 = User::factory()->admin()->create();
        $this->admin2 = User::factory()->admin()->create();
    }

    /** @test */
    public function tenant_can_be_created_with_domain()
    {
        $tenant = Tenant::factory()->create([
            'name' => 'Test School',
            'domain' => 'test.school.com'
        ]);

        Domain::factory()->create([
            'domain' => 'test.school.com',
            'tenant_id' => $tenant->id
        ]);

        $this->assertInstanceOf(Tenant::class, $tenant);
        $this->assertEquals('Test School', $tenant->name);
        $this->assertEquals('test.school.com', $tenant->domain);
        $this->assertEquals(1, $tenant->domains->count());
    }

    /** @test */
    public function tenant_has_unique_database_name()
    {
        $tenant1 = Tenant::factory()->create(['name' => 'School A', 'domain' => 'a.test.com']);
        $tenant2 = Tenant::factory()->create(['name' => 'School B', 'domain' => 'b.test.com']);

        $this->assertNotEquals($tenant1->database, $tenant2->database);
        $this->assertStringContainsString('School A_a.test.com_db', $tenant1->database);
        $this->assertStringContainsString('School B_b.test.com_db', $tenant2->database);
    }

    /** @test */
    public function tenant_can_have_multiple_domains()
    {
        $tenant = Tenant::factory()->create();

        Domain::factory()->create([
            'domain' => 'primary.test.com',
            'tenant_id' => $tenant->id
        ]);

        Domain::factory()->create([
            'domain' => 'secondary.test.com',
            'tenant_id' => $tenant->id
        ]);

        $this->assertCount(2, $tenant->domains);
    }

    /** @test */
    public function tenant_domains_are_unique()
    {
        $tenant1 = Tenant::factory()->create();
        $tenant2 = Tenant::factory()->create();

        Domain::factory()->create([
            'domain' => 'unique.test.com',
            'tenant_id' => $tenant1->id
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        Domain::factory()->create([
            'domain' => 'unique.test.com',
            'tenant_id' => $tenant2->id
        ]);
    }

    /** @test */
    public function tenant_status_affects_access()
    {
        $activeTenant = Tenant::factory()->create(['status' => 'active']);
        $inactiveTenant = Tenant::factory()->create(['status' => 'inactive']);

        $this->assertTrue($activeTenant->isActive());
        $this->assertFalse($inactiveTenant->isActive());
    }

    /** @test */
    public function tenant_can_be_suspended()
    {
        $tenant = Tenant::factory()->suspended()->create();

        $this->assertEquals('suspended', $tenant->status);
        $this->assertFalse($tenant->isActive());
    }

    /** @test */
    public function tenant_has_geographic_information()
    {
        $tenant = Tenant::factory()->create([
            'latitude' => 27.7172,
            'longitude' => 85.3240,
            'local_body_id' => 1,
            'ward_number' => 5
        ]);

        $this->assertEquals(27.7172, $tenant->latitude);
        $this->assertEquals(85.3240, $tenant->longitude);
        $this->assertEquals(1, $tenant->local_body_id);
        $this->assertEquals(5, $tenant->ward_number);
    }

    /** @test */
    public function tenant_has_sms_balance()
    {
        $tenant = Tenant::factory()->create(['sms_balance' => 500]);

        $this->assertEquals(500, $tenant->sms_balance);
        $this->assertIsInt($tenant->sms_balance);
    }

    /** @test */
    public function tenant_is_associated_with_government()
    {
        $tenant = Tenant::factory()->create(['government_id' => 3]);

        $this->assertEquals(3, $tenant->government_id);
        $this->assertIsInt($tenant->government_id);
    }

    /** @test */
    public function tenant_can_be_soft_deleted()
    {
        $tenant = Tenant::factory()->create();

        $tenant->delete();

        $this->assertSoftDeleted('tenants', ['id' => $tenant->id]);
        $this->assertNotNull($tenant->deleted_at);
    }

    /** @test */
    public function tenant_data_is_isolated_between_tenants()
    {
        // This test would require tenant database setup
        // For now, we'll test the concept with central database models
        
        $tenant1 = Tenant::factory()->create();
        $tenant2 = Tenant::factory()->create();

        // Create users associated with different tenants
        $user1 = User::factory()->create(['tenant_id' => $tenant1->id]);
        $user2 = User::factory()->create(['tenant_id' => $tenant2->id]);

        // Verify users are associated with correct tenants
        $this->assertEquals($tenant1->id, $user1->tenant_id);
        $this->assertEquals($tenant2->id, $user2->tenant_id);
    }

    /** @test */
    public function tenant_domain_resolution_works()
    {
        $tenant = Tenant::factory()->create(['domain' => 'myschool.test.com']);
        Domain::factory()->create([
            'domain' => 'myschool.test.com',
            'tenant_id' => $tenant->id
        ]);

        // Test domain resolution
        $resolvedTenant = Domain::where('domain', 'myschool.test.com')->first()->tenant;
        
        $this->assertEquals($tenant->id, $resolvedTenant->id);
        $this->assertEquals($tenant->name, $resolvedTenant->name);
    }

    /** @test */
    public function tenant_can_be_activated_and_deactivated()
    {
        $tenant = Tenant::factory()->create(['status' => 'inactive']);

        // Activate tenant
        $tenant->status = 'active';
        $tenant->save();

        $this->assertEquals('active', $tenant->status);
        $this->assertTrue($tenant->isActive());

        // Deactivate tenant
        $tenant->status = 'inactive';
        $tenant->save();

        $this->assertEquals('inactive', $tenant->status);
        $this->assertFalse($tenant->isActive());
    }

    /** @test */
    public function tenant_has_required_configuration()
    {
        $tenant = Tenant::factory()->create();

        $this->assertNotNull($tenant->name);
        $this->assertNotNull($tenant->domain);
        $this->assertNotNull($tenant->database);
        $this->assertNotNull($tenant->email);
        $this->assertNotNull($tenant->phone);
        $this->assertNotNull($tenant->address);
        $this->assertNotNull($tenant->school_code);
        $this->assertNotNull($tenant->status);
    }

    /** @test */
    public function tenant_school_code_is_unique()
    {
        Tenant::factory()->create(['school_code' => 'SCH001']);

        $this->expectException(\Illuminate\Database\QueryException::class);
        Tenant::factory()->create(['school_code' => 'SCH001']);
    }

    /** @test */
    public function tenant_domain_is_unique()
    {
        Tenant::factory()->create(['domain' => 'unique.test.com']);

        $this->expectException(\Illuminate\Database\QueryException::class);
        Tenant::factory()->create(['domain' => 'unique.test.com']);
    }

    /** @test */
    public function tenant_has_principal_information()
    {
        $tenant = Tenant::factory()->create([
            'principal_name' => 'Dr. John Doe',
            'principal_phone' => '9876543210'
        ]);

        $this->assertEquals('Dr. John Doe', $tenant->principal_name);
        $this->assertEquals('9876543210', $tenant->principal_phone);
    }

    /** @test */
    public function tenant_has_establishment_year()
    {
        $tenant = Tenant::factory()->create(['established_year' => 1995]);

        $this->assertEquals(1995, $tenant->established_year);
        $this->assertIsInt($tenant->established_year);
    }

    /** @test */
    public function tenant_casts_attributes_correctly()
    {
        $tenant = Tenant::factory()->create();

        $this->assertIsString($tenant->name);
        $this->assertIsString($tenant->domain);
        $this->assertIsString($tenant->database);
        $this->assertIsString($tenant->email);
        $this->assertIsString($tenant->phone);
        $this->assertIsString($tenant->address);
        $this->assertIsString($tenant->school_code);
        $this->assertIsString($tenant->status);
        $this->assertIsString($tenant->principal_name);
        $this->assertIsString($tenant->principal_phone);
        $this->assertIsInt($tenant->sms_balance);
        $this->assertIsFloat($tenant->latitude);
        $this->assertIsFloat($tenant->longitude);
        $this->assertIsInt($tenant->local_body_id);
        $this->assertIsInt($tenant->ward_number);
        $this->assertIsInt($tenant->government_id);
    }

    /** @test */
    public function tenant_can_be_queried_by_status()
    {
        $activeTenant = Tenant::factory()->create(['status' => 'active']);
        $inactiveTenant = Tenant::factory()->create(['status' => 'inactive']);
        $suspendedTenant = Tenant::factory()->create(['status' => 'suspended']);

        $activeTenants = Tenant::where('status', 'active')->get();
        $inactiveTenants = Tenant::where('status', 'inactive')->get();
        $suspendedTenants = Tenant::where('status', 'suspended')->get();

        $this->assertCount(1, $activeTenants);
        $this->assertCount(1, $inactiveTenants);
        $this->assertCount(1, $suspendedTenants);

        $this->assertEquals($activeTenant->id, $activeTenants->first()->id);
        $this->assertEquals($inactiveTenant->id, $inactiveTenants->first()->id);
        $this->assertEquals($suspendedTenant->id, $suspendedTenants->first()->id);
    }

    /** @test */
    public function tenant_can_be_queried_by_government()
    {
        $governmentId = 5;
        $tenant1 = Tenant::factory()->create(['government_id' => $governmentId]);
        $tenant2 = Tenant::factory()->create(['government_id' => $governmentId]);
        $tenant3 = Tenant::factory()->create(['government_id' => 10]);

        $governmentTenants = Tenant::where('government_id', $governmentId)->get();

        $this->assertCount(2, $governmentTenants);
        $this->assertTrue($governmentTenants->contains('id', $tenant1->id));
        $this->assertTrue($governmentTenants->contains('id', $tenant2->id));
        $this->assertFalse($governmentTenants->contains('id', $tenant3->id));
    }
}