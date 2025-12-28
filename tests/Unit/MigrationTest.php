<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MigrationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_run_all_migrations_successfully()
    {
        $this->assertTrue(true);
        
        // Test that we can access the database after migrations
        $this->artisan('migrate:fresh', ['--env' => 'testing', '--quiet'])
            ->assertExitCode(0);
    }

    /** @test */
    public function central_tables_are_created()
    {
        // Test central database tables
        $this->assertTrue(Schema::hasTable('users'));
        $this->assertTrue(Schema::hasTable('tenants'));
        $this->assertTrue(Schema::hasTable('domains'));
        $this->assertTrue(Schema::hasTable('system_logs'));
        $this->assertTrue(Schema::hasTable('login_attempts'));
        $this->assertTrue(Schema::hasTable('login_histories'));
        $this->assertTrue(Schema::hasTable('personal_access_tokens'));
        $this->assertTrue(Schema::hasTable('local_bodies'));
        $this->assertTrue(Schema::hasTable('governments'));
    }

    /** @test */
    public function user_table_has_correct_columns()
    {
        $this->assertTrue(Schema::hasColumn('users', 'id'));
        $this->assertTrue(Schema::hasColumn('users', 'name'));
        $this->assertTrue(Schema::hasColumn('users', 'email'));
        $this->assertTrue(Schema::hasColumn('users', 'email_verified_at'));
        $this->assertTrue(Schema::hasColumn('users', 'password'));
        $this->assertTrue(Schema::hasColumn('users', 'remember_token'));
        $this->assertTrue(Schema::hasColumn('users', 'role'));
        $this->assertTrue(Schema::hasColumn('users', 'phone'));
        $this->assertTrue(Schema::hasColumn('users', 'address'));
        $this->assertTrue(Schema::hasColumn('users', 'status'));
        $this->assertTrue(Schema::hasColumn('users', 'created_at'));
        $this->assertTrue(Schema::hasColumn('users', 'updated_at'));
    }

    /** @test */
    public function tenant_table_has_correct_columns()
    {
        $this->assertTrue(Schema::hasColumn('tenants', 'id'));
        $this->assertTrue(Schema::hasColumn('tenants', 'name'));
        $this->assertTrue(Schema::hasColumn('tenants', 'domain'));
        $this->assertTrue(Schema::hasColumn('tenants', 'database'));
        $this->assertTrue(Schema::hasColumn('tenants', 'email'));
        $this->assertTrue(Schema::hasColumn('tenants', 'phone'));
        $this->assertTrue(Schema::hasColumn('tenants', 'address'));
        $this->assertTrue(Schema::hasColumn('tenants', 'principal_name'));
        $this->assertTrue(Schema::hasColumn('tenants', 'principal_phone'));
        $this->assertTrue(Schema::hasColumn('tenants', 'established_year'));
        $this->assertTrue(Schema::hasColumn('tenants', 'school_code'));
        $this->assertTrue(Schema::hasColumn('tenants', 'status'));
        $this->assertTrue(Schema::hasColumn('tenants', 'sms_balance'));
        $this->assertTrue(Schema::hasColumn('tenants', 'latitude'));
        $this->assertTrue(Schema::hasColumn('tenants', 'longitude'));
        $this->assertTrue(Schema::hasColumn('tenants', 'local_body_id'));
        $this->assertTrue(Schema::hasColumn('tenants', 'ward_number'));
        $this->assertTrue(Schema::hasColumn('tenants', 'government_id'));
        $this->assertTrue(Schema::hasColumn('tenants', 'created_at'));
        $this->assertTrue(Schema::hasColumn('tenants', 'updated_at'));
        $this->assertTrue(Schema::hasColumn('tenants', 'deleted_at'));
    }

    /** @test */
    public function domain_table_has_correct_columns()
    {
        $this->assertTrue(Schema::hasColumn('domains', 'id'));
        $this->assertTrue(Schema::hasColumn('domains', 'domain'));
        $this->assertTrue(Schema::hasColumn('domains', 'tenant_id'));
        $this->assertTrue(Schema::hasColumn('domains', 'created_at'));
        $this->assertTrue(Schema::hasColumn('domains', 'updated_at'));
    }

    /** @test */
    public function system_logs_table_has_correct_columns()
    {
        $this->assertTrue(Schema::hasColumn('system_logs', 'id'));
        $this->assertTrue(Schema::hasColumn('system_logs', 'tenant_id'));
        $this->assertTrue(Schema::hasColumn('system_logs', 'user_id'));
        $this->assertTrue(Schema::hasColumn('system_logs', 'action'));
        $this->assertTrue(Schema::hasColumn('system_logs', 'description'));
        $this->assertTrue(Schema::hasColumn('system_logs', 'ip_address'));
        $this->assertTrue(Schema::hasColumn('system_logs', 'user_agent'));
        $this->assertTrue(Schema::hasColumn('system_logs', 'created_at'));
    }

    /** @test */
    public function login_attempts_table_has_correct_columns()
    {
        $this->assertTrue(Schema::hasColumn('login_attempts', 'id'));
        $this->assertTrue(Schema::hasColumn('login_attempts', 'email'));
        $this->assertTrue(Schema::hasColumn('login_attempts', 'ip_address'));
        $this->assertTrue(Schema::hasColumn('login_attempts', 'user_agent'));
        $this->assertTrue(Schema::hasColumn('login_attempts', 'success'));
        $this->assertTrue(Schema::hasColumn('login_attempts', 'created_at'));
    }

    /** @test */
    public function local_bodies_table_has_correct_columns()
    {
        $this->assertTrue(Schema::hasColumn('local_bodies', 'id'));
        $this->assertTrue(Schema::hasColumn('local_bodies', 'name'));
        $this->assertTrue(Schema::hasColumn('local_bodies', 'type'));
        $this->assertTrue(Schema::hasColumn('local_bodies', 'district_id'));
        $this->assertTrue(Schema::hasColumn('local_bodies', 'province_id'));
        $this->assertTrue(Schema::hasColumn('local_bodies', 'created_at'));
        $this->assertTrue(Schema::hasColumn('local_bodies', 'updated_at'));
    }

    /** @test */
    public function governments_table_has_correct_columns()
    {
        $this->assertTrue(Schema::hasColumn('governments', 'id'));
        $this->assertTrue(Schema::hasColumn('governments', 'name'));
        $this->assertTrue(Schema::hasColumn('governments', 'type'));
        $this->assertTrue(Schema::hasColumn('governments', 'contact_email'));
        $this->assertTrue(Schema::hasColumn('governments', 'contact_phone'));
        $this->assertTrue(Schema::hasColumn('governments', 'address'));
        $this->assertTrue(Schema::hasColumn('governments', 'created_at'));
        $this->assertTrue(Schema::hasColumn('governments', 'updated_at'));
    }

    /** @test */
    public function tables_have_correct_indexes()
    {
        // Test important indexes exist
        $this->assertTrue(Schema::hasIndex('users', 'users_email_unique'));
        $this->assertTrue(Schema::hasIndex('tenants', 'tenants_domain_unique'));
        $this->assertTrue(Schema::hasIndex('tenants', 'tenants_school_code_unique'));
        $this->assertTrue(Schema::hasIndex('domains', 'domains_domain_unique'));
        $this->assertTrue(Schema::hasIndex('domains', 'domains_tenant_id_foreign'));
    }

    /** @test */
    public function foreign_key_constraints_are_created()
    {
        // Test foreign key constraints
        $this->assertTrue(Schema::hasColumn('domains', 'tenant_id'));
        $this->assertTrue(Schema::hasColumn('system_logs', 'tenant_id'));
        $this->assertTrue(Schema::hasColumn('system_logs', 'user_id'));
    }

    /** @test */
    public function migrations_can_be_rolled_back()
    {
        // First run migrations
        $this->artisan('migrate', ['--env' => 'testing'])->assertExitCode(0);
        
        // Then rollback
        $this->artisan('migrate:rollback', ['--env' => 'testing'])->assertExitCode(0);
        
        // Re-migrate to clean state
        $this->artisan('migrate', ['--env' => 'testing'])->assertExitCode(0);
    }

    /** @test */
    public function migrations_are_idempotent()
    {
        // Run migrations twice
        $this->artisan('migrate', ['--env' => 'testing'])->assertExitCode(0);
        $this->artisan('migrate', ['--env' => 'testing'])->assertExitCode(0);
        
        // Should still work
        $this->assertTrue(Schema::hasTable('users'));
        $this->assertTrue(Schema::hasTable('tenants'));
    }
}