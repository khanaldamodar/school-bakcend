<?php

namespace Tests\Unit\Models;

use App\Models\Tenant;
use App\Models\Domain;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function tenant_can_be_created_with_factory()
    {
        $tenant = Tenant::factory()->create();

        $this->assertInstanceOf(Tenant::class, $tenant);
        $this->assertNotNull($tenant->name);
        $this->assertNotNull($tenant->domain);
        $this->assertNotNull($tenant->database);
    }

    /** @test */
    public function tenant_has_correct_database_naming_format()
    {
        $tenant = Tenant::factory()->create([
            'name' => 'Test School',
            'domain' => 'test.example.com'
        ]);

        $expectedDatabase = 'Test School_test.example.com_db';
        $this->assertEquals($expectedDatabase, $tenant->database);
    }

    /** @test */
    public function tenant_has_domain_relationship()
    {
        $tenant = Tenant::factory()->withDomain()->create();

        $this->assertInstanceOf(Domain::class, $tenant->domains);
        $this->assertEquals($tenant->domain, $tenant->domains->first()->domain);
    }

    /** @test */
    public function tenant_can_have_multiple_domains()
    {
        $tenant = Tenant::factory()->create();
        
        Domain::factory()->create(['domain' => 'primary.test.com', 'tenant_id' => $tenant->id]);
        Domain::factory()->create(['domain' => 'secondary.test.com', 'tenant_id' => $tenant->id]);

        $this->assertCount(2, $tenant->domains);
    }

    /** @test */
    public function tenant_status_can_be_active()
    {
        $tenant = Tenant::factory()->create(['status' => 'active']);
        
        $this->assertEquals('active', $tenant->status);
        $this->assertTrue($tenant->isActive());
    }

    /** @test */
    public function tenant_status_can_be_inactive()
    {
        $tenant = Tenant::factory()->inactive()->create();
        
        $this->assertEquals('inactive', $tenant->status);
        $this->assertFalse($tenant->isActive());
    }

    /** @test */
    public function tenant_status_can_be_suspended()
    {
        $tenant = Tenant::factory()->suspended()->create();
        
        $this->assertEquals('suspended', $tenant->status);
        $this->assertFalse($tenant->isActive());
    }

    /** @test */
    public function tenant_has_required_attributes()
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
        $tenant1 = Tenant::factory()->create(['school_code' => 'SCH001']);
        
        $this->expectException(\Illuminate\Database\QueryException::class);
        Tenant::factory()->create(['school_code' => 'SCH001']);
    }

    /** @test */
    public function tenant_domain_is_unique()
    {
        $tenant1 = Tenant::factory()->create(['domain' => 'unique.test.com']);
        
        $this->expectException(\Illuminate\Database\QueryException::class);
        Tenant::factory()->create(['domain' => 'unique.test.com']);
    }

    /** @test */
    public function tenant_has_geographic_coordinates()
    {
        $tenant = Tenant::factory()->create();

        $this->assertNotNull($tenant->latitude);
        $this->assertNotNull($tenant->longitude);
        $this->assertIsFloat($tenant->latitude);
        $this->assertIsFloat($tenant->longitude);
    }

    /** @test */
    public function tenant_has_local_body_information()
    {
        $tenant = Tenant::factory()->create();

        $this->assertNotNull($tenant->local_body_id);
        $this->assertNotNull($tenant->ward_number);
        $this->assertIsInt($tenant->local_body_id);
        $this->assertIsInt($tenant->ward_number);
    }

    /** @test */
    public function tenant_has_sms_balance()
    {
        $tenant = Tenant::factory()->create();

        $this->assertNotNull($tenant->sms_balance);
        $this->assertIsInt($tenant->sms_balance);
        $this->assertGreaterThanOrEqual(0, $tenant->sms_balance);
    }

    /** @test */
    public function tenant_has_government_association()
    {
        $tenant = Tenant::factory()->create();

        $this->assertNotNull($tenant->government_id);
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
    public function tenant_casts_attributes_correctly()
    {
        $tenant = Tenant::factory()->create();

        $this->assertIsInt($tenant->sms_balance);
        $this->assertIsFloat($tenant->latitude);
        $this->assertIsFloat($tenant->longitude);
        $this->assertIsInt($tenant->local_body_id);
        $this->assertIsInt($tenant->ward_number);
        $this->assertIsInt($tenant->government_id);
    }
}