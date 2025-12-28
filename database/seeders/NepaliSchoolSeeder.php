<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\Admin\AcademicYear;
use App\Models\Admin\ResultSetting;
use App\Models\Admin\Term;
use App\Models\Admin\SchoolClass;
use App\Models\Admin\Subject;
use App\Models\Admin\Teacher;
use App\Models\Admin\Student;
use App\Models\User;
use App\Models\Admin\Result;
use Carbon\Carbon;

class NepaliSchoolSeeder extends Seeder
{
    public function run()
    {
        // 1. Clear existing data
        // Order matters due to foreign keys
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        Result::truncate();
        Student::truncate();
        Subject::truncate();
        DB::table('class_subject_teacher')->truncate();
        SchoolClass::truncate();
        Teacher::truncate();
        Term::truncate();
        ResultSetting::truncate();
        AcademicYear::truncate();
        \App\Models\Admin\Setting::truncate(); // Add setting truncate
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $this->command->info('Existing data cleared.');

        // 2. Academic Years
        $year2080 = AcademicYear::create(['name' => '2080', 'start_date' => '2023-04-14', 'end_date' => '2024-04-12']);
        $year2081 = AcademicYear::create(['name' => '2081', 'start_date' => '2024-04-13', 'end_date' => '2025-04-13', 'is_current' => true]);

        // Create Setting
        $schoolSetting = \App\Models\Admin\Setting::create([
            'name' => 'Shree Nepal Rastriya Secondary School',
            'address' => 'Kathmandu, Nepal',
            'phone' => '01-4000000',
            'email' => 'info@school.com',
            'school_type' => 'Secondary',
            'established_date' => '2010-01-01',
            'number_of_exams' => 3
        ]);

        // 3. Result Settings (Applied for 2081)
        $setting = ResultSetting::create([
            'setting_id' => $schoolSetting->id,
            'academic_year_id' => $year2081->id,
            'result_type' => 'percentage', // Percentage based system
            'evaluation_per_term' => true,  // Evaluation per term
            'calculation_method' => 'simple', // Simple average/sum
            'term_weights' => null,
            'total_terms' => 3
        ]);

        // 4. Terms
        $terms = [
            ['name' => 'First Term', 'weight' => 20],
            ['name' => 'Second Term', 'weight' => 30],
            ['name' => 'Final Term', 'weight' => 50],
        ];

        foreach ($terms as $t) {
            Term::create([
                'result_setting_id' => $setting->id,
                'academic_year_id' => $year2081->id,
                'name' => $t['name'],
                'weight' => $t['weight'],
                'start_date' => now(), // placeholder
                'end_date' => now()->addMonths(3)
            ]);
        }
        $firstTerm = Term::where('name', 'First Term')->first();
        $secondTerm = Term::where('name', 'Second Term')->first();

        // 5. Teachers
        $teacherUser = User::create([
            'name' => 'Ram Bahadur Teacher',
            'email' => 'teacher@school.com',
            'password' => Hash::make('password'),
            'role' => 'teacher',
        ]);
        
        $teacher = Teacher::create([
            'user_id' => $teacherUser->id,
            'name' => 'Ram Bahadur',
            'email' => 'teacher@school.com',
            'phone' => '9800000001',
            'gender' => 'Male',
            'dob' => '1985-01-01',
            'address' => 'Kathmandu',
            'joining_date' => '2020-01-01',
            'qualification' => 'B.Ed'
        ]);

        // 6. Classes
        $classesData = [
            ['name' => 'Class 1', 'section' => 'A'],
            ['name' => 'Class 5', 'section' => 'A'],
            ['name' => 'Class 8', 'section' => 'A'],
            ['name' => 'Class 10', 'section' => 'A'],
        ];

        $createdClasses = [];
        foreach ($classesData as $c) {
            $createdClasses[] = SchoolClass::create($c);
        }
        
        $class8 = SchoolClass::where('name', 'Class 8')->first();

        // 7. Subjects (Nepali Curriculum)
        $subjectsList = [
            ['name' => 'Nepali', 'code' => 'NEP', 'theory' => 75, 'practical' => 25, 'theory_pass' => 27, 'practical_pass' => 10],
            ['name' => 'English', 'code' => 'ENG', 'theory' => 75, 'practical' => 25, 'theory_pass' => 27, 'practical_pass' => 10],
            ['name' => 'Mathematics', 'code' => 'MTH', 'theory' => 100, 'practical' => 0, 'theory_pass' => 40, 'practical_pass' => 0],
            ['name' => 'Science', 'code' => 'SCI', 'theory' => 75, 'practical' => 25, 'theory_pass' => 27, 'practical_pass' => 10],
            ['name' => 'Social Studies', 'code' => 'SOC', 'theory' => 75, 'practical' => 25, 'theory_pass' => 27, 'practical_pass' => 10],
            ['name' => 'Health & Physical', 'code' => 'HPE', 'theory' => 30, 'practical' => 20, 'theory_pass' => 12, 'practical_pass' => 8], // Assuming 50FM for demo
        ];

        foreach ($createdClasses as $cls) {
            foreach ($subjectsList as $sub) {
                $subject = Subject::create([
                    'name' => $sub['name'],
                    'subject_code' => $sub['code'] . '-' . $cls->id, // Unique code per class
                    'theory_marks' => $sub['theory'],
                    'practical_marks' => $sub['practical'],
                    'theory_pass_marks' => $sub['theory_pass'],
                    'practical_pass_marks' => $sub['practical_pass'],
                    'teacher_id' => $teacher->user_id
                ]);

                // Attach to class via Pivot
                DB::table('class_subject_teacher')->insert([
                    'class_id' => $cls->id,
                    'subject_id' => $subject->id,
                    'teacher_id' => $teacher->id,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }
        }

        // 8. Students (Class 8 Focus)
        $studentNames = ['Aarav Sharma', 'Bibek Thapa', 'Chanda Karki', 'Deepak Khanal', 'Elisha Rai', 'Firoj Alam', 'Gita Basnet', 'Hari Krishna'];
        $roll = 1;

        foreach ($studentNames as $name) {
            $nameParts = explode(' ', $name);
            $user = User::create([
                'name' => $name,
                'email' => strtolower($nameParts[0]) . '@student.com',
                'password' => Hash::make('password'),
                'role' => 'student'
            ]);

            Student::create([
                'user_id' => $user->id,
                'class_id' => $class8->id,
                'first_name' => $nameParts[0],
                'last_name' => $nameParts[1] ?? '',
                'roll_number' => $roll++,
                'enrollment_year' => '2081',
                'gender' => $roll % 2 == 0 ? 'Female' : 'Male',
                'dob' => '2010-01-01',
                'address' => 'Kathmandu',
                'phone' => '981111111' . $roll,
                'is_disabled' => false,
                'is_tribe' => false
            ]);
        }

        // 9. Generate Results for Class 8 (First Term) - For Demo
        // Only for subjects of Class 8
        $class8Subjects = $class8->subjects;
        $class8Students = Student::where('class_id', $class8->id)->get();
        
        $calculationService = new \App\Services\ResultCalculationService();

        foreach ($class8Students as $student) {
            foreach ($class8Subjects as $subject) {
                // Determine random marks
                // 80% chance of passing
                $isPass = rand(1, 100) <= 80;
                
                if ($isPass) {
                    $theoryObtained = rand($subject->theory_pass_marks, $subject->theory_marks);
                    $practicalObtained = $subject->practical_marks > 0 ? rand($subject->practical_pass_marks, $subject->practical_marks) : 0;
                } else {
                    $theoryObtained = rand(0, $subject->theory_pass_marks - 1);
                    $practicalObtained = $subject->practical_marks > 0 ? rand(0, $subject->practical_pass_marks - 1) : 0;
                }

                $totalObtained = $theoryObtained + $practicalObtained;
                $totalMax = $subject->theory_marks + $subject->practical_marks;
                
                // Calculate GPA/Grade
                $percentage = ($totalObtained / $totalMax) * 100;
                $gpa = $calculationService->calculateGPA($totalObtained, $totalMax);
                $grade = $calculationService->getGradeFromPercentage($percentage);
                $remarks = $isPass ? 'Good' : 'Needs Improvement';

                Result::create([
                    'academic_year_id' => $year2081->id,
                    'term_id' => $firstTerm->id,
                    'class_id' => $class8->id,
                    'student_id' => $student->id,
                    'subject_id' => $subject->id,
                    'teacher_id' => $teacher->id, // Use standard teacher
                    'exam_type' => 'First Term',
                    'exam_date' => '2024-07-15',
                    'marks_theory' => $theoryObtained,
                    'marks_practical' => $practicalObtained,
                    'gpa' => $gpa,
                    'percentage' => round($percentage, 2),
                    'remarks' => $remarks
                ]);
            }
        }

        $this->command->info('Nepali School Data Seeded Successfully!');
    }
}
