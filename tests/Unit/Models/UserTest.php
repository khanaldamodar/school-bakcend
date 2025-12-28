<?php

namespace Tests\Unit\Models;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function user_can_be_created_with_factory()
    {
        $user = User::factory()->create();

        $this->assertInstanceOf(User::class, $user);
        $this->assertNotNull($user->name);
        $this->assertNotNull($user->email);
        $this->assertNotNull($user->password);
    }

    /** @test */
    public function user_has_required_attributes()
    {
        $user = User::factory()->create();

        $this->assertNotNull($user->name);
        $this->assertNotNull($user->email);
        $this->assertNotNull($user->password);
        $this->assertNotNull($user->role);
        $this->assertNotNull($user->status);
    }

    /** @test */
    public function user_email_is_unique()
    {
        User::factory()->create(['email' => 'test@example.com']);
        
        $this->expectException(\Illuminate\Database\QueryException::class);
        User::factory()->create(['email' => 'test@example.com']);
    }

    /** @test */
    public function user_password_is_hashed()
    {
        $plainPassword = 'password123';
        $user = User::factory()->create(['password' => $plainPassword]);

        $this->assertNotEquals($plainPassword, $user->password);
        $this->assertTrue(Hash::check($plainPassword, $user->password));
    }

    /** @test */
    public function user_has_admin_role()
    {
        $user = User::factory()->admin()->create();
        
        $this->assertEquals('admin', $user->role);
        $this->assertTrue($user->isAdmin());
    }

    /** @test */
    public function user_has_teacher_role()
    {
        $user = User::factory()->teacher()->create();
        
        $this->assertEquals('teacher', $user->role);
        $this->assertTrue($user->isTeacher());
    }

    /** @test */
    public function user_has_student_role()
    {
        $user = User::factory()->student()->create();
        
        $this->assertEquals('student', $user->role);
        $this->assertTrue($user->isStudent());
    }

    /** @test */
    public function user_has_parent_role()
    {
        $user = User::factory()->parent()->create();
        
        $this->assertEquals('parent', $user->role);
        $this->assertTrue($user->isParent());
    }

    /** @test */
    public function user_has_superadmin_role()
    {
        $user = User::factory()->superadmin()->create();
        
        $this->assertEquals('superadmin', $user->role);
        $this->assertTrue($user->isSuperAdmin());
    }

    /** @test */
    public function user_has_government_role()
    {
        $user = User::factory()->government()->create();
        
        $this->assertEquals('government', $user->role);
        $this->assertTrue($user->isGovernment());
    }

    /** @test */
    public function user_status_can_be_active()
    {
        $user = User::factory()->create(['status' => 'active']);
        
        $this->assertEquals('active', $user->status);
        $this->assertTrue($user->isActive());
    }

    /** @test */
    public function user_status_can_be_inactive()
    {
        $user = User::factory()->inactive()->create();
        
        $this->assertEquals('inactive', $user->status);
        $this->assertFalse($user->isActive());
    }

    /** @test */
    public function user_can_be_unverified()
    {
        $user = User::factory()->unverified()->create();
        
        $this->assertNull($user->email_verified_at);
        $this->assertFalse($user->hasVerifiedEmail());
    }

    /** @test */
    public function user_can_be_verified()
    {
        $user = User::factory()->create();
        
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue($user->hasVerifiedEmail());
    }

    /** @test */
    public function user_has_contact_information()
    {
        $user = User::factory()->create();

        $this->assertNotNull($user->phone);
        $this->assertNotNull($user->address);
        $this->assertIsString($user->phone);
        $this->assertIsString($user->address);
    }

    /** @test */
    public function user_can_be_soft_deleted()
    {
        $user = User::factory()->create();
        
        $user->delete();
        
        $this->assertSoftDeleted('users', ['id' => $user->id]);
        $this->assertNotNull($user->deleted_at);
    }

    /** @test */
    public function user_has_remember_token()
    {
        $user = User::factory()->create();

        $this->assertNotNull($user->remember_token);
        $this->assertIsString($user->remember_token);
    }

    /** @test */
    public function user_role_is_valid()
    {
        $validRoles = ['admin', 'teacher', 'student', 'parent', 'superadmin', 'government'];
        
        foreach ($validRoles as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->assertEquals($role, $user->role);
        }
    }

    /** @test */
    public function user_status_is_valid()
    {
        $validStatuses = ['active', 'inactive'];
        
        foreach ($validStatuses as $status) {
            $user = User::factory()->create(['status' => $status]);
            $this->assertEquals($status, $user->status);
        }
    }

    /** @test */
    public function user_can_generate_api_tokens()
    {
        $user = User::factory()->create();
        
        $token = $user->createToken('test-token');
        
        $this->assertNotNull($token);
        $this->assertNotNull($token->accessToken);
    }
}