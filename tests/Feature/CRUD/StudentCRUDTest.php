<?php

namespace Tests\Feature\CRUD;

use App\Models\User;
use App\Models\Admin\Student;
use App\Models\Admin\SchoolClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class StudentCRUDTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->admin = User::factory()->admin()->create();
        $this->token = $this->admin->createToken('test-token')->plainTextToken;
    }

    /** @test */
    public function admin_can_create_student()
    {
        $class = SchoolClass::factory()->create();
        $studentData = [
            'first_name' => 'Ram',
            'last_name' => 'Bahadur',
            'date_of_birth' => '2005-01-15',
            'gender' => 'male',
            'phone' => '9876543210',
            'email' => 'ram@example.com',
            'address' => 'Kathmandu, Nepal',
            'class_id' => $class->id,
            'roll_number' => 1,
            'section' => 'A',
            'blood_group' => 'A+',
            'nationality' => 'Nepalese',
            'religion' => 'Hindu',
            'mother_tongue' => 'Nepali',
            'city' => 'Kathmandu',
            'district' => 'Kathmandu',
            'province' => 3,
            'postal_code' => '44600',
            'country' => 'Nepal',
            'emergency_contact_name' => 'Father Name',
            'emergency_contact_phone' => '9876543211',
            'emergency_contact_relation' => 'Father',
            'admission_date' => '2023-01-01',
            'admission_number' => 'ADM001',
            'transport_required' => false,
            'hostel_required' => false,
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->postJson('/api/admin/students', $studentData);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'id',
                    'student_id',
                    'first_name',
                    'last_name',
                    'full_name',
                    'email',
                    'phone',
                    'class_id',
                    'roll_number',
                    'status',
                    'created_at'
                ]);

        $this->assertDatabaseHas('students', [
            'first_name' => 'Ram',
            'last_name' => 'Bahadur',
            'email' => 'ram@example.com',
            'class_id' => $class->id,
            'roll_number' => 1
        ]);
    }

    /** @test */
    public function admin_cannot_create_student_without_required_fields()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->postJson('/api/admin/students', []);

        $response->assertStatus(422)
                ->assertJsonValidationErrors([
                    'first_name',
                    'last_name',
                    'date_of_birth',
                    'gender',
                    'class_id',
                    'roll_number'
                ]);
    }

    /** @test */
    public function admin_cannot_create_student_with_duplicate_roll_number_in_same_class()
    {
        $class = SchoolClass::factory()->create();
        
        // Create first student
        Student::factory()->create([
            'class_id' => $class->id,
            'roll_number' => 1
        ]);

        $studentData = [
            'first_name' => 'Second',
            'last_name' => 'Student',
            'date_of_birth' => '2005-01-15',
            'gender' => 'male',
            'class_id' => $class->id,
            'roll_number' => 1, // Duplicate roll number
            'section' => 'A',
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->postJson('/api/admin/students', $studentData);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['roll_number']);
    }

    /** @test */
    public function admin_can_list_students()
    {
        Student::factory()->count(5)->create();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->getJson('/api/admin/students');

        $response->assertStatus(200)
                ->assertJsonCount(5, 'data')
                ->assertJsonStructure([
                    'data' => [
                        '*' => [
                            'id',
                            'student_id',
                            'first_name',
                            'last_name',
                            'full_name',
                            'email',
                            'phone',
                            'class_id',
                            'roll_number',
                            'status'
                        ]
                    ],
                    'links',
                    'meta'
                ]);
    }

    /** @test */
    public function admin_can_filter_students_by_class()
    {
        $class1 = SchoolClass::factory()->create();
        $class2 = SchoolClass::factory()->create();
        
        Student::factory()->count(3)->create(['class_id' => $class1->id]);
        Student::factory()->count(2)->create(['class_id' => $class2->id]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->getJson("/api/admin/students?class_id={$class1->id}");

        $response->assertStatus(200)
                ->assertJsonCount(3, 'data');
    }

    /** @test */
    public function admin_can_search_students_by_name()
    {
        Student::factory()->create(['first_name' => 'Ram', 'last_name' => 'Bahadur']);
        Student::factory()->create(['first_name' => 'Sita', 'last_name' => 'Sharma']);
        Student::factory()->create(['first_name' => 'Ram', 'last_name' => 'Kumar']);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->getJson('/api/admin/students?search=Ram');

        $response->assertStatus(200)
                ->assertJsonCount(2, 'data');
    }

    /** @test */
    public function admin_can_view_single_student()
    {
        $student = Student::factory()->create();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->getJson("/api/admin/students/{$student->id}");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'id',
                    'student_id',
                    'first_name',
                    'last_name',
                    'full_name',
                    'date_of_birth',
                    'gender',
                    'email',
                    'phone',
                    'address',
                    'class_id',
                    'roll_number',
                    'section',
                    'blood_group',
                    'nationality',
                    'religion',
                    'mother_tongue',
                    'city',
                    'district',
                    'province',
                    'postal_code',
                    'country',
                    'emergency_contact_name',
                    'emergency_contact_phone',
                    'emergency_contact_relation',
                    'admission_date',
                    'admission_number',
                    'transport_required',
                    'hostel_required',
                    'status',
                    'created_at',
                    'updated_at'
                ]);
    }

    /** @test */
    public function admin_cannot_view_nonexistent_student()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->getJson('/api/admin/students/999');

        $response->assertStatus(404)
                ->assertJson(['message' => 'Student not found']);
    }

    /** @test */
    public function admin_can_update_student()
    {
        $student = Student::factory()->create();
        $updateData = [
            'first_name' => 'Updated',
            'last_name' => 'Name',
            'phone' => '9876543210',
            'address' => 'Updated Address',
            'blood_group' => 'B+',
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->putJson("/api/admin/students/{$student->id}", $updateData);

        $response->assertStatus(200)
                ->assertJson([
                    'first_name' => 'Updated',
                    'last_name' => 'Name',
                    'phone' => '9876543210',
                    'address' => 'Updated Address',
                    'blood_group' => 'B+',
                ]);

        $this->assertDatabaseHas('students', [
            'id' => $student->id,
            'first_name' => 'Updated',
            'last_name' => 'Name',
        ]);
    }

    /** @test */
    public function admin_cannot_update_student_with_invalid_data()
    {
        $student = Student::factory()->create();
        $updateData = [
            'first_name' => '',
            'email' => 'invalid-email',
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->putJson("/api/admin/students/{$student->id}", $updateData);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['first_name', 'email']);
    }

    /** @test */
    public function admin_can_delete_student()
    {
        $student = Student::factory()->create();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->deleteJson("/api/admin/students/{$student->id}");

        $response->assertStatus(200)
                ->assertJson(['message' => 'Student deleted successfully']);

        $this->assertSoftDeleted('students', ['id' => $student->id]);
    }

    /** @test */
    public function admin_cannot_delete_nonexistent_student()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->deleteJson('/api/admin/students/999');

        $response->assertStatus(404)
                ->assertJson(['message' => 'Student not found']);
    }

    /** @test */
    public function admin_can_upload_student_photo()
    {
        $student = Student::factory()->create();
        $file = UploadedFile::fake()->image('student.jpg');

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->postJson("/api/admin/students/{$student->id}/upload-photo", [
            'photo' => $file
        ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'message',
                    'photo_url'
                ]);
    }

    /** @test */
    public function admin_cannot_upload_invalid_file_as_student_photo()
    {
        $student = Student::factory()->create();
        $file = UploadedFile::fake()->create('document.pdf', 1000);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->postJson("/api/admin/students/{$student->id}/upload-photo", [
            'photo' => $file
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['photo']);
    }

    /** @test */
    public function admin_can_bulk_import_students()
    {
        $csvContent = "first_name,last_name,email,phone,class_id,roll_number\n";
        $csvContent .= "Test1,Student1,test1@example.com,9876543210,1,1\n";
        $csvContent .= "Test2,Student2,test2@example.com,9876543211,1,2\n";

        $file = UploadedFile::fake()->createWithContent('students.csv', $csvContent);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->postJson('/api/admin/students/bulk-import', [
            'file' => $file
        ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'message',
                    'imported_count',
                    'failed_count',
                    'errors'
                ]);
    }

    /** @test */
    public function admin_can_export_students_to_csv()
    {
        Student::factory()->count(3)->create();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->getJson('/api/admin/students/export');

        $response->assertStatus(200)
                ->assertHeader('content-type', 'text/csv; charset=UTF-8')
                ->assertHeader('content-disposition');
    }

    /** @test */
    public function teacher_cannot_create_student()
    {
        $teacher = User::factory()->teacher()->create();
        $token = $teacher->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->postJson('/api/admin/students', [
            'first_name' => 'Test',
            'last_name' => 'Student',
        ]);

        $response->assertStatus(403)
                ->assertJson(['message' => 'Unauthorized access']);
    }

    /** @test */
    public function teacher_cannot_delete_student()
    {
        $teacher = User::factory()->teacher()->create();
        $token = $teacher->createToken('test-token')->plainTextToken;
        $student = Student::factory()->create();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->deleteJson("/api/admin/students/{$student->id}");

        $response->assertStatus(403)
                ->assertJson(['message' => 'Unauthorized access']);
    }

    /** @test */
    public function unauthenticated_user_cannot_access_student_endpoints()
    {
        $response = $this->getJson('/api/admin/students');

        $response->assertStatus(401)
                ->assertJson(['message' => 'Unauthenticated.']);
    }
}