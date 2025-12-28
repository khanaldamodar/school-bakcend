<?php

namespace Database\Factories\Tenant;

use App\Models\Admin\SchoolClass;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Admin\SchoolClass>
 */
class SchoolClassFactory extends Factory
{
    protected $model = SchoolClass::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->randomElement(['Grade 1', 'Grade 2', 'Grade 3', 'Grade 4', 'Grade 5', 'Grade 6', 'Grade 7', 'Grade 8', 'Grade 9', 'Grade 10']),
            'section' => $this->faker->randomElement(['A', 'B', 'C', 'D']),
            'capacity' => $this->faker->numberBetween(30, 60),
            'current_strength' => $this->faker->numberBetween(20, 55),
            'class_teacher_id' => null, // Will be set in afterCreating callback
            'floor' => $this->faker->numberBetween(1, 4),
            'room_number' => $this->faker->numerify('Room ###'),
            'status' => 'active',
            'academic_year_id' => 1, // Will be updated based on actual academic year
        ];
    }

    /**
     * Create an inactive class.
     */
    public function inactive(): static
    {
        return $this->state(fn () => [
            'status' => 'inactive',
        ]);
    }

    /**
     * Create a class with full capacity.
     */
    public function full(): static
    {
        return $this->state(fn () => [
            'current_strength' => fn (array $attributes) => $attributes['capacity'],
        ]);
    }
}