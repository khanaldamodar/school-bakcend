<?php

namespace Database\Factories\Tenant;

use App\Models\Admin\Teacher;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Admin\Teacher>
 */
class TeacherFactory extends Factory
{
    protected $model = Teacher::class;

    public function definition(): array
    {
        $firstName = $this->faker->firstName();
        $lastName = $this->faker->lastName();
        
        return [
            'user_id' => User::factory()->teacher(),
            'teacher_id' => $this->faker->unique()->numerify('TCH#####'),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'full_name' => "{$firstName} {$lastName}",
            'date_of_birth' => $this->faker->date('Y-m-d', '1990-01-01'),
            'gender' => $this->faker->randomElement(['male', 'female']),
            'blood_group' => $this->faker->randomElement(['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-']),
            'nationality' => 'Nepalese',
            'phone' => $this->faker->phoneNumber(),
            'email' => $this->faker->unique()->safeEmail(),
            'address' => $this->faker->address(),
            'city' => $this->faker->city(),
            'district' => $this->faker->randomElement(['Kathmandu', 'Lalitpur', 'Bhaktapur', 'Pokhara', 'Chitwan']),
            'province' => $this->faker->numberBetween(1, 7),
            'postal_code' => $this->faker->postcode(),
            'country' => 'Nepal',
            'emergency_contact_name' => $this->faker->name(),
            'emergency_contact_phone' => $this->faker->phoneNumber(),
            'emergency_contact_relation' => $this->faker->randomElement(['Spouse', 'Parent', 'Sibling', 'Friend']),
            'joining_date' => $this->faker->dateTimeBetween('-5 years', 'now'),
            'employment_type' => $this->faker->randomElement(['permanent', 'temporary', 'contract']),
            'designation' => $this->faker->randomElement(['Senior Teacher', 'Teacher', 'Assistant Teacher', 'Subject Teacher']),
            'department' => $this->faker->randomElement(['Science', 'Mathematics', 'English', 'Social Studies', 'Arts']),
            'qualification' => $this->faker->randomElement(['Bachelors', 'Masters', 'PhD']),
            'specialization' => $this->faker->optional()->jobTitle(),
            'experience_years' => $this->faker->numberBetween(1, 20),
            'previous_school' => $this->faker->optional()->company(),
            'pan_number' => $this->faker->numerify('#########'),
            'bank_name' => $this->faker->optional()->randomElement(['Nabil Bank', 'NIC Asia', 'Standard Chartered', 'Himalayan Bank']),
            'bank_account' => $this->faker->optional()->numerify('##############'),
            'salary' => $this->faker->numberBetween(30000, 100000),
            'status' => 'active',
            'medical_conditions' => $this->faker->optional()->text(),
            'remarks' => $this->faker->optional()->text(),
        ];
    }

    /**
     * Create an inactive teacher.
     */
    public function inactive(): static
    {
        return $this->state(fn () => [
            'status' => 'inactive',
        ]);
    }

    /**
     * Create a senior teacher.
     */
    public function senior(): static
    {
        return $this->state(fn () => [
            'designation' => 'Senior Teacher',
            'experience_years' => $this->faker->numberBetween(10, 25),
            'qualification' => 'Masters',
        ]);
    }

    /**
     * Create a temporary teacher.
     */
    public function temporary(): static
    {
        return $this->state(fn () => [
            'employment_type' => 'temporary',
        ]);
    }

    /**
     * Create a contract teacher.
     */
    public function contract(): static
    {
        return $this->state(fn () => [
            'employment_type' => 'contract',
        ]);
    }
}