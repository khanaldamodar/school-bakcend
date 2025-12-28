<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SimpleMigrationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_has_basic_tables_after_migration()
    {
        // Just test that basic tables exist after refresh database
        $this->assertTrue(Schema::hasTable('users'));
        $this->assertTrue(Schema::hasTable('migrations'));
    }

    /** @test */
    public function it_has_user_table_columns()
    {
        $this->assertTrue(Schema::hasColumn('users', 'id'));
        $this->assertTrue(Schema::hasColumn('users', 'name'));
        $this->assertTrue(Schema::hasColumn('users', 'email'));
    }

    /** @test */
    public function it_handles_database_connection()
    {
        // Test that database is accessible
        $this->assertNotNull(DB::connection());
    }
}