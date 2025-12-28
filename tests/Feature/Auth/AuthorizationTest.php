<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function admin_can_access_admin_endpoints()
    {
        $admin = User::factory()->admin()->create();
        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->getJson('/api/admin/dashboard');

        $response->assertStatus(200);
    }

    /** @test */
    public function teacher_cannot_access_admin_endpoints()
    {
        $teacher = User::factory()->teacher()->create();
        $token = $teacher->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->getJson('/api/admin/dashboard');

        $response->assertStatus(403)
                ->assertJson(['message' => 'Unauthorized access']);
    }

    /** @test */
    public function student_cannot_access_admin_endpoints()
    {
        $student = User::factory()->student()->create();
        $token = $student->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->getJson('/api/admin/dashboard');

        $response->assertStatus(403)
                ->assertJson(['message' => 'Unauthorized access']);
    }

    /** @test */
    public function parent_cannot_access_admin_endpoints()
    {
        $parent = User::factory()->parent()->create();
        $token = $parent->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->getJson('/api/admin/dashboard');

        $response->assertStatus(403)
                ->assertJson(['message' => 'Unauthorized access']);
    }

    /** @test */
    public function teacher_can_access_teacher_endpoints()
    {
        $teacher = User::factory()->teacher()->create();
        $token = $teacher->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->getJson('/api/teacher/dashboard');

        $response->assertStatus(200);
    }

    /** @test */
    public function student_cannot_access_teacher_endpoints()
    {
        $student = User::factory()->student()->create();
        $token = $student->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->getJson('/api/teacher/dashboard');

        $response->assertStatus(403)
                ->assertJson(['message' => 'Unauthorized access']);
    }

    /** @test */
    public function student_can_access_student_endpoints()
    {
        $student = User::factory()->student()->create();
        $token = $student->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->getJson('/api/student/dashboard');

        $response->assertStatus(200);
    }

    /** @test */
    public function teacher_cannot_access_student_endpoints()
    {
        $teacher = User::factory()->teacher()->create();
        $token = $teacher->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->getJson('/api/student/dashboard');

        $response->assertStatus(403)
                ->assertJson(['message' => 'Unauthorized access']);
    }

    /** @test */
    public function parent_can_access_parent_endpoints()
    {
        $parent = User::factory()->parent()->create();
        $token = $parent->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->getJson('/api/parent/dashboard');

        $response->assertStatus(200);
    }

    /** @test */
    public function student_cannot_access_parent_endpoints()
    {
        $student = User::factory()->student()->create();
        $token = $student->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->getJson('/api/parent/dashboard');

        $response->assertStatus(403)
                ->assertJson(['message' => 'Unauthorized access']);
    }

    /** @test */
    public function superadmin_can_access_superadmin_endpoints()
    {
        $superadmin = User::factory()->superadmin()->create();
        $token = $superadmin->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->getJson('/api/superadmin/dashboard');

        $response->assertStatus(200);
    }

    /** @test */
    public function admin_cannot_access_superadmin_endpoints()
    {
        $admin = User::factory()->admin()->create();
        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->getJson('/api/superadmin/dashboard');

        $response->assertStatus(403)
                ->assertJson(['message' => 'Unauthorized access']);
    }

    /** @test */
    public function government_can_access_government_endpoints()
    {
        $government = User::factory()->government()->create();
        $token = $government->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->getJson('/api/government/dashboard');

        $response->assertStatus(200);
    }

    /** @test */
    public function admin_cannot_access_government_endpoints()
    {
        $admin = User::factory()->admin()->create();
        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->getJson('/api/government/dashboard');

        $response->assertStatus(403)
                ->assertJson(['message' => 'Unauthorized access']);
    }

    /** @test */
    public function inactive_user_cannot_access_any_protected_endpoints()
    {
        $inactiveUser = User::factory()->inactive()->create();
        $token = $inactiveUser->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->getJson('/api/admin/dashboard');

        $response->assertStatus(404)
                ->assertJson(['message' => 'Account is inactive']);
    }

    /** @test */
    public function user_without_token_cannot_access_protected_endpoints()
    {
        $response = $this->getJson('/api/admin/dashboard');

        $response->assertStatus(404)
                ->assertJson(['message' => 'Unauthenticated.']);
    }

    /** @test */
    public function user_with_invalid_token_cannot_access_protected_endpoints()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer invalid-token'
        ])->getJson('/api/admin/dashboard');

        $response->assertStatus(404)
                ->assertJson(['message' => 'Unauthenticated.']);
    }

    /** @test */
    public function admin_can_manage_students()
    {
        $admin = User::factory()->admin()->create();
        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->getJson('/api/admin/students');

        $response->assertStatus(200);
    }

    /** @test */
    public function teacher_cannot_manage_students()
    {
        $teacher = User::factory()->teacher()->create();
        $token = $teacher->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->getJson('/api/admin/students');

        $response->assertStatus(403)
                ->assertJson(['message' => 'Unauthorized access']);
    }

    /** @test */
    public function admin_can_manage_teachers()
    {
        $admin = User::factory()->admin()->create();
        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->getJson('/api/admin/teachers');

        $response->assertStatus(200);
    }

    /** @test */
    public function student_cannot_manage_teachers()
    {
        $student = User::factory()->student()->create();
        $token = $student->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->getJson('/api/admin/teachers');

        $response->assertStatus(403)
                ->assertJson(['message' => 'Unauthorized access']);
    }

    /** @test */
    public function admin_can_manage_classes()
    {
        $admin = User::factory()->admin()->create();
        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->getJson('/api/admin/classes');

        $response->assertStatus(200);
    }

    /** @test */
    public function parent_cannot_manage_classes()
    {
        $parent = User::factory()->parent()->create();
        $token = $parent->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->getJson('/api/admin/classes');

        $response->assertStatus(403)
                ->assertJson(['message' => 'Unauthorized access']);
    }

    /** @test */
    public function superadmin_can_manage_tenants()
    {
        $superadmin = User::factory()->superadmin()->create();
        $token = $superadmin->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->getJson('/api/superadmin/tenants');

        $response->assertStatus(200);
    }

    /** @test */
    public function admin_cannot_manage_tenants()
    {
        $admin = User::factory()->admin()->create();
        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->getJson('/api/superadmin/tenants');

        $response->assertStatus(403)
                ->assertJson(['message' => 'Unauthorized access']);
    }
}