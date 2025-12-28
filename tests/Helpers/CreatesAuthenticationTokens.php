<?php

namespace Tests\Helpers;

use App\Models\User;
use Laravel\Sanctum\Sanctum;

trait CreatesAuthenticationTokens
{
    /**
     * Create and authenticate a user with a specific role.
     */
    protected function authenticateUser(string $role = 'admin'): User
    {
        $user = User::factory()->create(['role' => $role]);
        Sanctum::actingAs($user);
        
        return $user;
    }

    /**
     * Create and return an admin user with API token.
     */
    protected function createAdmin(): User
    {
        return User::factory()->admin()->create();
    }

    /**
     * Create and return a teacher user with API token.
     */
    protected function createTeacher(): User
    {
        return User::factory()->teacher()->create();
    }

    /**
     * Create and return a student user with API token.
     */
    protected function createStudent(): User
    {
        return User::factory()->student()->create();
    }

    /**
     * Create and return a parent user with API token.
     */
    protected function createParent(): User
    {
        return User::factory()->parent()->create();
    }

    /**
     * Create and return a superadmin user with API token.
     */
    protected function createSuperAdmin(): User
    {
        return User::factory()->superadmin()->create();
    }

    /**
     * Create and return a government user with API token.
     */
    protected function createGovernment(): User
    {
        return User::factory()->government()->create();
    }

    /**
     * Get API token for a user.
     */
    protected function getApiToken(User $user): string
    {
        return $user->createToken('test-token')->plainTextToken;
    }

    /**
     * Set authentication headers for API requests.
     */
    protected function withAuth(User $user): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->getApiToken($user),
            'Accept' => 'application/json',
            'Content-Type' => 'application/json'
        ];
    }
}