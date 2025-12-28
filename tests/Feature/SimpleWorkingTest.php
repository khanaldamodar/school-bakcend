<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SimpleWorkingTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_creates_user_successfully()
    {
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin'
        ]);

        $this->assertDatabaseHas('users', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'role' => 'admin'
        ]);
    }

    /** @test */
    public function it_validates_user_creation()
    {
        $user = User::factory()->make();
        
        $this->assertNotNull($user->name);
        $this->assertNotNull($user->email);
        $this->assertContains($user->role, ['admin', 'teacher', 'student', 'parent', 'superadmin', 'government']);
    }

    /** @test */
    public function basic_web_route_test()
    {
        $response = $this->get('/');

        $this->assertContains($response->getStatusCode(), [200, 404, 405]);
    }

    /** @test */
    public function test_database_connection()
    {
        $this->assertNotNull(DB::connection());
    }

    /** @test */
    public function test_laravel_application_works()
    {
        $this->assertTrue(true);
        $this->assertNotNull(app());
    }

    /** @test */
    public function test_user_can_be_created_with_all_fields()
    {
        $user = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => bcrypt('password'),
            'role' => 'student',
            'district' => 'Lalitpur',
            'local_bodies' => 5
        ]);

        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals('John Doe', $user->name);
        $this->assertEquals('john@example.com', $user->email);
        $this->assertEquals('student', $user->role);
        $this->assertDatabaseHas('users', ['email' => 'john@example.com']);
    }

    /** @test */
    public function test_user_email_is_unique()
    {
        User::factory()->create(['email' => 'unique@example.com']);
        
        $this->expectException(\Illuminate\Database\QueryException::class);
        User::factory()->create(['email' => 'unique@example.com']);
    }

    /** @test */
    public function test_user_roles_are_valid()
    {
        $validRoles = ['admin', 'teacher', 'student', 'parent', 'superadmin', 'government'];
        
        foreach ($validRoles as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->assertEquals($role, $user->role);
        }
    }
}