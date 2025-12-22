<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\Student;
use App\Models\Admin\StudentClassHistory;
use App\Models\Admin\AcademicYear;
use App\Models\Admin\SchoolClass;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\TenantLogger;

class StudentPromotionController extends Controller
{
    /**
     * Promote students from one class to another
     * This is typically used at the end of an academic year
     */
    public function promoteClass(Request $request)
    {
        $validated = $request->validate([
            'from_class_id' => 'required|exists:classes,id',
            'to_class_id' => 'required|exists:classes,id|different:from_class_id',
            'student_ids' => 'nullable|array', // If empty, promote all students
            'student_ids.*' => 'exists:students,id',
            'academic_year_id' => 'nullable|exists:academic_years,id',
            'remarks' => 'nullable|string',
        ]);

        DB::beginTransaction();

        try {
            $fromClassId = $validated['from_class_id'];
            $toClassId = $validated['to_class_id'];
            $studentIds = $validated['student_ids'] ?? [];
            $academicYearId = $validated['academic_year_id'] ?? AcademicYear::current()?->id;

            // Get students to promote
            $query = Student::where('class_id', $fromClassId);
            
            if (!empty($studentIds)) {
                $query->whereIn('id', $studentIds);
            }

            $students = $query->get();

            if ($students->isEmpty()) {
                return response()->json([
                    'status' => false,
                    'message' => 'No students found to promote',
                ], 404);
            }

            $promotedCount = 0;
            $toClass = SchoolClass::find($toClassId);

            foreach ($students as $student) {
                // Mark old class history as promoted
                StudentClassHistory::where('student_id', $student->id)
                    ->where('class_id', $fromClassId)
                    ->where('status', 'active')
                    ->update([
                        'status' => 'promoted',
                        'promoted_date' => now(),
                        'remarks' => $validated['remarks'] ?? 'Promoted to ' . $toClass->name
                    ]);

                // Update student's current class
                $student->update(['class_id' => $toClassId]);

                $promotedCount++;
            }

            // Reassign roll numbers for both classes
            $this->reassignRollNumbers($fromClassId);
            $this->reassignRollNumbers($toClassId);

            // Create new history records for promoted students
            $students->each(function ($student) use ($toClassId, $academicYearId, $validated) {
                $student->refresh(); // Get updated roll number
                
                StudentClassHistory::create([
                    'student_id' => $student->id,
                    'class_id' => $toClassId,
                    'year' => now()->year,
                    'academic_year_id' => $academicYearId,
                    'roll_number' => $student->roll_number,
                    'status' => 'active',
                    'remarks' => $validated['remarks'] ?? 'Promoted from previous class',
                ]);
            });

            DB::commit();

            TenantLogger::studentInfo('Students promoted successfully', [
                'from_class_id' => $fromClassId,
                'to_class_id' => $toClassId,
                'count' => $promotedCount
            ]);

            return response()->json([
                'status' => true,
                'message' => "$promotedCount student(s) promoted successfully",
                'data' => [
                    'promoted_count' => $promotedCount,
                    'from_class_id' => $fromClassId,
                    'to_class_id' => $toClassId,
                ]
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Student promotion failed', ['error' => $e->getMessage()]);
            TenantLogger::studentError('Student promotion failed', ['error' => $e->getMessage()]);
            
            return response()->json([
                'status' => false,
                'message' => 'Failed to promote students',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get student history for a specific student
     */
    public function getStudentHistory($domain, $studentId)
    {
        $student = Student::findOrFail($studentId);

        $history = StudentClassHistory::with(['class', 'academicYear'])
            ->where('student_id', $studentId)
            ->orderBy('year', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'status' => true,
            'message' => 'Student history fetched successfully',
            'data' => [
                'student' => $student,
                'history' => $history
            ]
        ]);
    }

    /**
     * Get class history (all students who were in a class during an academic year)
     */
    public function getClassHistory(Request $request, $domain, $classId)
    {
        $validated = $request->validate([
            'academic_year_id' => 'nullable|exists:academic_years,id',
            'status' => 'nullable|in:active,promoted,transferred,graduated',
        ]);

        $query = StudentClassHistory::with(['student', 'academicYear'])
            ->where('class_id', $classId);

        if (isset($validated['academic_year_id'])) {
            $query->where('academic_year_id', $validated['academic_year_id']);
        }

        if (isset($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        $history = $query->orderBy('roll_number')->get();

        return response()->json([
            'status' => true,
            'message' => 'Class history fetched successfully',
            'data' => $history
        ]);
    }

    /**
     * Mark students as graduated
     */
    public function markGraduated(Request $request)
    {
        $validated = $request->validate([
            'student_ids' => 'required|array|min:1',
            'student_ids.*' => 'exists:students,id',
            'remarks' => 'nullable|string',
        ]);

        DB::beginTransaction();

        try {
            $students = Student::whereIn('id', $validated['student_ids'])->get();

            foreach ($students as $student) {
                // Mark current class history as graduated
                StudentClassHistory::where('student_id', $student->id)
                    ->where('status', 'active')
                    ->update([
                        'status' => 'graduated',
                        'promoted_date' => now(),
                        'remarks' => $validated['remarks'] ?? 'Graduated'
                    ]);

                // Optionally mark student as transferred or add a graduated flag
                // You might want to add a 'status' column to students table
            }

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => count($validated['student_ids']) . ' student(s) marked as graduated',
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Failed to mark students as graduated', ['error' => $e->getMessage()]);
            
            return response()->json([
                'status' => false,
                'message' => 'Failed to mark students as graduated',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reassign roll numbers for all students in a class
     */
    private function reassignRollNumbers($classId)
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
