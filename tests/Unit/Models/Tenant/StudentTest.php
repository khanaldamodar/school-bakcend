<?php

namespace Tests\Unit\Models\Tenant;

use App\Models\Admin\Student;
use App\Models\Admin\SchoolClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function student_can_be_created_with_factory()
    {
        $student = Student::factory()->create();

        $this->assertInstanceOf(Student::class, $student);
        $this->assertNotNull($student->student_id);
        $this->assertNotNull($student->first_name);
        $this->assertNotNull($student->last_name);
    }

    /** @test */
    public function student_has_user_relationship()
    {
        $student = Student::factory()->create();

        $this->assertInstanceOf(User::class, $student->user);
        $this->assertEquals('student', $student->user->role);
    }

    /** @test */
    public function student_has_class_relationship()
    {
        $student = Student::factory()->create();

        $this->assertInstanceOf(SchoolClass::class, $student->class);
    }

    /** @test */
    public function student_has_results_relationship()
    {
        $student = Student::factory()->create();
        $this->actingAs($student->user);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $student->results);
    }

    /** @test */
    public function student_has_required_attributes()
    {
        $student = Student::factory()->create();

        $this->assertNotNull($student->student_id);
        $this->assertNotNull($student->first_name);
        $this->assertNotNull($student->last_name);
        $this->assertNotNull($student->full_name);
        $this->assertNotNull($student->date_of_birth);
        $this->assertNotNull($student->gender);
        $this->assertNotNull($student->user_id);
        $this->assertNotNull($student->class_id);
        $this->assertNotNull($student->roll_number);
    }

    /** @test */
    public function student_full_name_is_concatenated()
    {
        $student = Student::factory()->create([
            'first_name' => 'Ram',
            'last_name' => 'Bahadur'
        ]);

        $this->assertEquals('Ram Bahadur', $student->full_name);
    }

    /** @test */
    public function student_id_is_unique()
    {
        Student::factory()->create(['student_id' => 'SB1234001']);
        
        $this->expectException(\Illuminate\Database\QueryException::class);
        Student::factory()->create(['student_id' => 'SB1234001']);
    }

    /** @test */
    public function student_roll_number_is_unique_within_class()
    {
        $class = SchoolClass::factory()->create();
        
        Student::factory()->create([
            'class_id' => $class->id,
            'roll_number' => 1
        ]);
        
        $this->expectException(\Illuminate\Database\QueryException::class);
        Student::factory()->create([
            'class_id' => $class->id,
            'roll_number' => 1
        ]);
    }

    /** @test */
    public function student_status_can_be_active()
    {
        $student = Student::factory()->create(['status' => 'active']);
        
        $this->assertEquals('active', $student->status);
        $this->assertTrue($student->isActive());
    }

    /** @test */
    public function student_status_can_be_inactive()
    {
        $student = Student::factory()->inactive()->create();
        
        $this->assertEquals('inactive', $student->status);
        $this->assertFalse($student->isActive());
    }

    /** @test */
    public function student_status_can_be_graduated()
    {
        $student = Student::factory()->graduated()->create();
        
        $this->assertEquals('graduated', $student->status);
        $this->assertFalse($student->isActive());
    }

    /** @test */
    public function student_status_can_be_transferred()
    {
        $student = Student::factory()->transferred()->create();
        
        $this->assertEquals('transferred', $student->status);
        $this->assertFalse($student->isActive());
    }

    /** @test */
    public function student_has_personal_information()
    {
        $student = Student::factory()->create();

        $this->assertNotNull($student->date_of_birth);
        $this->assertNotNull($student->gender);
        $this->assertNotNull($student->blood_group);
        $this->assertNotNull($student->nationality);
        $this->assertNotNull($student->religion);
        $this->assertNotNull($student->mother_tongue);
    }

    /** @test */
    public function student_has_contact_information()
    {
        $student = Student::factory()->create();

        $this->assertNotNull($student->phone);
        $this->assertNotNull($student->email);
        $this->assertNotNull($student->address);
        $this->assertNotNull($student->city);
        $this->assertNotNull($student->district);
        $this->assertNotNull($student->province);
        $this->assertNotNull($student->postal_code);
        $this->assertNotNull($student->country);
    }

    /** @test */
    public function student_has_emergency_contact_information()
    {
        $student = Student::factory()->create();

        $this->assertNotNull($student->emergency_contact_name);
        $this->assertNotNull($student->emergency_contact_phone);
        $this->assertNotNull($student->emergency_contact_relation);
    }

    /** @test */
    public function student_has_academic_information()
    {
        $student = Student::factory()->create();

        $this->assertNotNull($student->admission_date);
        $this->assertNotNull($student->admission_number);
        $this->assertNotNull($student->class_id);
        $this->assertNotNull($student->section);
        $this->assertNotNull($student->roll_number);
    }

    /** @test */
    public function student_can_require_transport()
    {
        $student = Student::factory()->withTransport()->create();
        
        $this->assertTrue($student->transport_required);
    }

    /** @test */
    public function student_can_require_hostel()
    {
        $student = Student::factory()->inHostel()->create();
        
        $this->assertTrue($student->hostel_required);
    }

    /** @test */
    public function student_has_medical_information()
    {
        $student = Student::factory()->create([
            'medical_conditions' => 'Asthma',
            'allergies' => 'Peanuts'
        ]);

        $this->assertEquals('Asthma', $student->medical_conditions);
        $this->assertEquals('Peanuts', $student->allergies);
    }

    /** @test */
    public function student_is_not_deleted_by_default()
    {
        $student = Student::factory()->create();
        
        $this->assertFalse($student->is_deleted);
    }

    /** @test */
    public function student_can_be_soft_deleted()
    {
        $student = Student::factory()->create();
        
        $student->delete();
        
        $this->assertSoftDeleted('students', ['id' => $student->id]);
    }

    /** @test */
    public function student_has_global_scope_to_exclude_deleted()
    {
        $activeStudent = Student::factory()->create();
        $deletedStudent = Student::factory()->create();
        $deletedStudent->is_deleted = true;
        $deletedStudent->save();

        $students = Student::all();
        
        $this->assertCount(1, $students);
        $this->assertEquals($activeStudent->id, $students->first()->id);
    }

    /** @test */
    public function student_can_be_found_without_global_scopes()
    {
        $activeStudent = Student::factory()->create();
        $deletedStudent = Student::factory()->create();
        $deletedStudent->is_deleted = true;
        $deletedStudent->save();

        $allStudents = Student::withoutGlobalScopes()->get();
        
        $this->assertCount(2, $allStudents);
    }

    /** @test */
    public function student_casts_attributes_correctly()
    {
        $student = Student::factory()->create();

        $this->assertIsString($student->student_id);
        $this->assertIsString($student->first_name);
        $this->assertIsString($student->last_name);
        $this->assertIsString($student->full_name);
        $this->assertIsInt($student->roll_number);
        $this->assertIsInt($student->user_id);
        $this->assertIsInt($student->class_id);
    }
}