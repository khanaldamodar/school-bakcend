<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => 'admin', // Use simple role for testing
            'district' => fake()->randomElement(['Kathmandu', 'Lalitpur', 'Bhaktapur', 'Pokhara']),
            'local_bodies' => fake()->numberBetween(1, 50),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn () => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Create an admin user.
     */
    public function admin(): static
    {
        return $this->state(fn () => [
            'role' => 'admin',
        ]);
    }

    /**
     * Create a teacher user.
     */
    public function teacher(): static
    {
        return $this->state(fn () => [
            'role' => 'teacher',
        ]);
    }

    /**
     * Create a student user.
     */
    public function student(): static
    {
        return $this->state(fn () => [
            'role' => 'student',
        ]);
    }

    /**
     * Create a parent user.
     */
    public function parent(): static
    {
        return $this->state(fn () => [
            'role' => 'parent',
        ]);
    }

    /**
     * Create a superadmin user.
     */
    public function superadmin(): static
    {
        return $this->state(fn () => [
            'role' => 'superadmin',
        ]);
    }

    /**
     * Create a government user.
     */
    public function government(): static
    {
        return $this->state(fn () => [
            'role' => 'government',
        ]);
    }

    /**
     * Create an inactive user.
     */
    public function inactive(): static
    {
        return $this->state(fn () => [
            'status' => 'inactive',
        ]);
    }
}
