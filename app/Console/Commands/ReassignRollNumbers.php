<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Admin\SchoolClass;
use App\Models\Admin\Student;

class ReassignRollNumbers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'students:reassign-roll-numbers {--class_id= : Specific class ID to reassign}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reassign roll numbers for all students in alphabetical order';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $classId = $this->option('class_id');

        if ($classId) {
            // Reassign for specific class
            $class = SchoolClass::find($classId);
            
            if (!$class) {
                $this->error("Class with ID {$classId} not found!");
                return 1;
            }

            $this->info("Reassigning roll numbers for class: {$class->name}");
            $this->reassignClassRollNumbers($classId);
            $this->info("✓ Completed for class: {$class->name}");
        } else {
            // Reassign for all classes
            $classes = SchoolClass::all();
            $this->info("Reassigning roll numbers for all classes...");
            
            $bar = $this->output->createProgressBar(count($classes));
            $bar->start();

            foreach ($classes as $class) {
                $this->reassignClassRollNumbers($class->id);
                $bar->advance();
            }

            $bar->finish();
            $this->newLine();
            $this->info("✓ Completed for all classes!");
        }

        return 0;
    }

    /**
     * Reassign roll numbers for a specific class
     */
    private function reassignClassRollNumbers($classId)
    {
        $students = Student::where('class_id', $classId)
            ->get()
            ->map(function ($student) {
                return [
                    'id' => $student->id,
                    'full_name' => trim(
                        ($student->first_name ?? '') . ' ' . 
                        ($student->middle_name ?? '') . ' ' . 
                        ($student->last_name ?? '')
                    )
                ];
            })
            ->sortBy(function ($student) {
                return strtolower($student['full_name']);
            })
            ->values();
        
        foreach ($students as $index => $student) {
            Student::where('id', $student['id'])
                ->update(['roll_number' => $index + 1]);
        }
    }
}
