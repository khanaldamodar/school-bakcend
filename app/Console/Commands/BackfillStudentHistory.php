<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Admin\Student;
use App\Models\Admin\StudentClassHistory;
use App\Models\Admin\AcademicYear;
use Illuminate\Support\Facades\DB;

class BackfillStudentHistory extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'students:backfill-history {--force : Skip confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create initial history records for existing students';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $students = Student::whereDoesntHave('histories')->get();

        if ($students->isEmpty()) {
            $this->info('All students already have history records!');
            return 0;
        }

        $this->info("Found {$students->count()} students without history records.");

        if (!$this->option('force')) {
            if (!$this->confirm('Do you want to create history records for these students?')) {
                $this->info('Operation cancelled.');
                return 0;
            }
        }

        $currentYear = AcademicYear::current();

        if (!$currentYear) {
            $this->warn('No current academic year set!');
            
            if ($this->confirm('Do you want to create one now?')) {
                $name = $this->ask('Academic year name (e.g., 2024-2025)');
                $startDate = $this->ask('Start date (YYYY-MM-DD)');
                $endDate = $this->ask('End date (YYYY-MM-DD)');

                $currentYear = AcademicYear::create([
                    'name' => $name,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'is_current' => true,
                ]);

                $this->info("Academic year '{$name}' created successfully!");
            } else {
                $this->info('Cannot proceed without an academic year.');
                return 1;
            }
        }

        $bar = $this->output->createProgressBar($students->count());
        $bar->start();

        $created = 0;

        DB::beginTransaction();

        try {
            foreach ($students as $student) {
                StudentClassHistory::create([
                    'student_id' => $student->id,
                    'class_id' => $student->class_id,
                    'year' => now()->year,
                    'academic_year_id' => $currentYear->id,
                    'roll_number' => $student->roll_number,
                    'status' => 'active',
                    'remarks' => 'Initial history record (backfilled)',
                ]);

                $created++;
                $bar->advance();
            }

            DB::commit();
            $bar->finish();
            $this->newLine(2);
            $this->info("✓ Successfully created {$created} history records!");

        } catch (\Exception $e) {
            DB::rollBack();
            $bar->finish();
            $this->newLine(2);
            $this->error("Failed to create history records: " . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
