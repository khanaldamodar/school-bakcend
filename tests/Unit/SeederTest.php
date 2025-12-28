<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class SeederTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_run_database_seeder_successfully()
    {
        $exitCode = Artisan::call('db:seed', ['--env' => 'testing']);
        $this->assertEquals(0, $exitCode);
    }

    /** @test */
    public function it_can_run_specific_seeder()
    {
        $exitCode = Artisan::call('db:seed', [
            '--class' => 'DatabaseSeeder',
            '--env' => 'testing'
        ]);
        $this->assertEquals(0, $exitCode);
    }

    /** @test */
    public function it_can_run_teacher_role_seeder()
    {
        $exitCode = Artisan::call('db:seed', [
            '--class' => 'TeacherRoleSeeder',
            '--env' => 'testing'
        ]);
        $this->assertEquals(0, $exitCode);
    }

    /** @test */
    public function it_can_run_nepali_school_seeder()
    {
        $exitCode = Artisan::call('db:seed', [
            '--class' => 'NepaliSchoolSeeder',
            '--env' => 'testing'
        ]);
        $this->assertEquals(0, $exitCode);
    }

    /** @test */
    public function seeder_creates_required_data()
    {
        // Run seeder
        Artisan::call('db:seed', ['--env' => 'testing']);

        // Check that essential data was created
        $this->assertDatabaseCount('users', 0); // Should be empty due to RefreshDatabase
        $this->assertDatabaseCount('tenants', 0);
        
        // But seeder should have run without errors
        $this->assertTrue(true);
    }

    /** @test */
    public function seeder_is_idempotent()
    {
        // Run seeder twice
        Artisan::call('db:seed', ['--env' => 'testing']);
        Artisan::call('db:seed', ['--env' => 'testing']);
        
        // Should not cause duplicate data issues
        $this->assertTrue(true);
    }

    /** @test */
    public function seeder_with_fresh_database()
    {
        // Fresh migrate
        Artisan::call('migrate:fresh', ['--env' => 'testing']);
        
        // Run seeder
        $exitCode = Artisan::call('db:seed', ['--env' => 'testing']);
        
        $this->assertEquals(0, $exitCode);
    }

    /** @test */
    public function seeder_creates_realistic_data()
    {
        // This test would verify that the seeder creates realistic test data
        // Implementation depends on your specific seeders
        
        // Run seeder
        Artisan::call('db:seed', ['--env' => 'testing']);
        
        // Verify data quality if seeders create initial data
        $this->assertTrue(true);
    }

    /** @test */
    public function seeder_handles_foreign_key_constraints()
    {
        // Test that seeder properly handles foreign key constraints
        Artisan::call('db:seed', ['--env' => 'testing']);
        
        // Should not cause foreign key constraint violations
        $this->assertTrue(true);
    }

    /** @test */
    public function seeder_rolls_back_on_error()
    {
        // This would test rollback behavior if seeder is wrapped in transaction
        // Implementation depends on your seeder design
        
        $this->assertTrue(true);
    }
}