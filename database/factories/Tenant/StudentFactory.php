<?php

namespace Database\Factories\Tenant;

use App\Models\Admin\Student;
use App\Models\Admin\SchoolClass;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Admin\Student>
 */
class StudentFactory extends Factory
{
    protected $model = Student::class;

    public function definition(): array
    {
        $firstName = $this->faker->firstName();
        $lastName = $this->faker->lastName();
        $localBodyCode = $this->faker->numerify('##');
        $studentIdNumber = $this->faker->unique()->numerify('#######');
        
        return [
            'user_id' => User::factory()->student(),
            'student_id' => "{$localBodyCode}-{$studentIdNumber}",
            'first_name' => $firstName,
            'last_name' => $lastName,
            'full_name' => "{$firstName} {$lastName}",
            'date_of_birth' => $this->faker->date('Y-m-d', '2008-01-01'),
            'gender' => $this->faker->randomElement(['male', 'female']),
            'blood_group' => $this->faker->randomElement(['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-']),
            'nationality' => 'Nepalese',
            'religion' => $this->faker->randomElement(['Hindu', 'Buddhist', 'Christian', 'Muslim', 'Other']),
            'mother_tongue' => $this->faker->randomElement(['Nepali', 'English', 'Maithili', 'Bhojpuri', 'Other']),
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
            'emergency_contact_relation' => $this->faker->randomElement(['Father', 'Mother', 'Guardian', 'Sibling']),
            'previous_school' => $this->faker->optional()->company(),
            'admission_date' => $this->faker->dateTimeBetween('-2 years', 'now'),
            'admission_number' => $this->faker->unique()->numerify('ADM#####'),
            'roll_number' => $this->faker->unique()->numberBetween(1, 1000),
            'class_id' => SchoolClass::factory(),
            'section' => $this->faker->randomElement(['A', 'B', 'C', 'D']),
            'house' => $this->faker->optional()->randomElement(['Red', 'Blue', 'Green', 'Yellow']),
            'transport_required' => $this->faker->boolean(30),
            'hostel_required' => $this->faker->boolean(20),
            'status' => 'active',
            'medical_conditions' => $this->faker->optional()->text(),
            'allergies' => $this->faker->optional()->text(),
            'special_needs' => $this->faker->optional()->text(),
            'remarks' => $this->faker->optional()->text(),
        ];
    }

    /**
     * Create an inactive student.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'inactive',
        ]);
    }

    /**
     * Create a graduated student.
     */
    public function graduated(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'graduated',
        ]);
    }

    /**
     * Create a transferred student.
     */
    public function transferred(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'transferred',
        ]);
    }

    /**
     * Create a student with transport.
     */
    public function withTransport(): static
    {
        return $this->state(fn (array $attributes) => [
            'transport_required' => true,
        ]);
    }

    /**
     * Create a student in hostel.
     */
    public function inHostel(): static
    {
        return $this->state(fn (array $attributes) => [
            'hostel_required' => true,
        ]);
    }
}