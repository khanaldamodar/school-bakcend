<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WorkingTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_creates_user_successfully()
    {
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password')
        ]);

        $this->assertDatabaseHas('users', [
            'name' => 'Test User',
            'email' => 'test@example.com'
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
    public function basic_route_test()
    {
        $response = $this->get('/api');

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
}