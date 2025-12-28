<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function user_can_login_with_valid_credentials()
    {
        $user = User::factory()->create([
            'password' => bcrypt('password123')
        ]);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password123'
        ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'user' => [
                        'id',
                        'name',
                        'email',
                        'role'
                    ],
                    'token'
                ]);

        $this->assertAuthenticatedAs($user);
    }

    /** @test */
    public function user_cannot_login_with_invalid_credentials()
    {
        $user = User::factory()->create();

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'wrongpassword'
        ]);

        $response->assertStatus(401)
                ->assertJson(['message' => 'Invalid credentials']);

        $this->assertGuest();
    }

    /** @test */
    public function user_cannot_login_with_nonexistent_email()
    {
        $response = $this->postJson('/api/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'password123'
        ]);

        $response->assertStatus(401)
                ->assertJson(['message' => 'Invalid credentials']);

        $this->assertGuest();
    }

    /** @test */
    public function login_requires_email_and_password()
    {
        $response = $this->postJson('/api/login', []);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['email', 'password']);
    }

    /** @test */
    public function login_requires_valid_email_format()
    {
        $response = $this->postJson('/api/login', [
            'email' => 'invalid-email',
            'password' => 'password123'
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['email']);
    }

    /** @test */
    public function user_can_logout_with_valid_token()
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->postJson('/api/logout');

        $response->assertStatus(200)
                ->assertJson(['message' => 'Logged out successfully']);

        $this->assertGuest();
    }

    /** @test */
    public function user_cannot_logout_without_token()
    {
        $response = $this->postJson('/api/logout');

        $response->assertStatus(401)
                ->assertJson(['message' => 'Unauthenticated.']);
    }

    /** @test */
    public function user_cannot_logout_with_invalid_token()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer invalid-token'
        ])->postJson('/api/logout');

        $response->assertStatus(401)
                ->assertJson(['message' => 'Unauthenticated.']);
    }

    /** @test */
    public function user_can_get_profile_with_valid_token()
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->getJson('/api/user/profile');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'id',
                    'name',
                    'email',
                    'role',
                    'phone',
                    'address',
                    'status',
                    'created_at'
                ]);
    }

    /** @test */
    public function user_cannot_get_profile_without_token()
    {
        $response = $this->getJson('/api/user/profile');

        $response->assertStatus(401)
                ->assertJson(['message' => 'Unauthenticated.']);
    }

    /** @test */
    public function user_can_update_profile_with_valid_token()
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->putJson('/api/user/profile', [
            'name' => 'Updated Name',
            'phone' => '9876543210',
            'address' => 'Updated Address'
        ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'id',
                    'name',
                    'email',
                    'role',
                    'phone',
                    'address',
                    'status'
                ]);

        $this->assertEquals('Updated Name', $response->json('name'));
        $this->assertEquals('9876543210', $response->json('phone'));
        $this->assertEquals('Updated Address', $response->json('address'));
    }

    /** @test */
    public function user_cannot_update_profile_without_token()
    {
        $response = $this->putJson('/api/user/profile', [
            'name' => 'Updated Name'
        ]);

        $response->assertStatus(401)
                ->assertJson(['message' => 'Unauthenticated.']);
    }

    /** @test */
    public function inactive_user_cannot_login()
    {
        $user = User::factory()->inactive()->create([
            'password' => bcrypt('password123')
        ]);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password123'
        ]);

        $response->assertStatus(401)
                ->assertJson(['message' => 'Account is inactive']);

        $this->assertGuest();
    }

    /** @test */
    public function user_can_change_password_with_valid_token()
    {
        $user = User::factory()->create([
            'password' => bcrypt('oldpassword')
        ]);
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->postJson('/api/user/change-password', [
            'current_password' => 'oldpassword',
            'new_password' => 'newpassword123',
            'new_password_confirmation' => 'newpassword123'
        ]);

        $response->assertStatus(200)
                ->assertJson(['message' => 'Password changed successfully']);

        // Verify new password works
        $this->assertTrue(Hash::check('newpassword123', $user->fresh()->password));
    }

    /** @test */
    public function user_cannot_change_password_with_wrong_current_password()
    {
        $user = User::factory()->create([
            'password' => bcrypt('oldpassword')
        ]);
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->postJson('/api/user/change-password', [
            'current_password' => 'wrongpassword',
            'new_password' => 'newpassword123',
            'new_password_confirmation' => 'newpassword123'
        ]);

        $response->assertStatus(422)
                ->assertJson(['message' => 'Current password is incorrect']);
    }

    /** @test */
    public function user_cannot_change_password_without_token()
    {
        $response = $this->postJson('/api/user/change-password', [
            'current_password' => 'oldpassword',
            'new_password' => 'newpassword123',
            'new_password_confirmation' => 'newpassword123'
        ]);

        $response->assertStatus(401)
                ->assertJson(['message' => 'Unauthenticated.']);
    }

    /** @test */
    public function password_change_requires_password_confirmation()
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->postJson('/api/user/change-password', [
            'current_password' => 'oldpassword',
            'new_password' => 'newpassword123',
            'new_password_confirmation' => 'differentpassword'
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['new_password']);
    }

    /** @test */
    public function login_attempts_are_rate_limited()
    {
        $user = User::factory()->create();

        // Make multiple failed login attempts
        for ($i = 0; $i < 6; $i++) {
            $response = $this->postJson('/api/login', [
                'email' => $user->email,
                'password' => 'wrongpassword'
            ]);
        }

        // The last attempt should be rate limited
        $response->assertStatus(429)
                ->assertJson(['message' => 'Too many login attempts.']);
    }
}