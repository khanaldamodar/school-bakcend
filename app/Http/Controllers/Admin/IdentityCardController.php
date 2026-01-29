<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\Request;

class IdentityCardController extends Controller
{
      public function bulkPrint(Request $request)
    {
        $request->validate([
            'class_id' => 'required|exists:classes,id',
        ]);

        $query = Student::with(['class', 'parents'])
            ->where('class_id', $request->class_id)
            ->where('is_deleted', false);

        $students = $query->get();

        $data = $students->map(function ($student) {
            return $this->formatStudentForIdentityCard($student);
        });

        return response()->json([
            'status' => true,
            'message' => 'Identity cards data fetched successfully',
            'data' => $data,
        ]);
    }

    /**
     * Print individual identity card for a student.
     * Can search by primary key ID or custom student_id.
     * Optionally validates against class_id if provided.
     *
     * @param Request $request
     * @param mixed $domain
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function individualPrint(Request $request, $domain, $id)
    {
        $query = Student::with(['class', 'parents'])
            ->where('is_deleted', false)
            ->where(function ($q) use ($id) {
                $q->where('id', $id)
                  ->orWhere('student_id', $id);
            });

        if ($request->has('class_id')) {
            $query->where('class_id', $request->class_id);
        }

        $student = $query->firstOrFail();

        return response()->json([
            'status' => true,
            'message' => 'Identity card data fetched successfully',
            'data' => $this->formatStudentForIdentityCard($student),
        ]);
    }

    /**
     * Format student data for identity card response.
     *
     * @param Student $student
     * @return array
     */
    private function formatStudentForIdentityCard($student)
    {
        // Parent name logic: Prioritize Father, then Mother, then Guardian/Others
        $parent = $student->parents->first(function ($p) {
            return stripos($p->relation, 'father') !== false;
        });

        if (!$parent) {
            $parent = $student->parents->first(function ($p) {
                return stripos($p->relation, 'mother') !== false;
            });
        }

        if (!$parent) {
            $parent = $student->parents->first();
        }

        $parentName = $parent 
            ? trim($parent->first_name . ' ' . ($parent->middle_name ? $parent->middle_name . ' ' : '') . $parent->last_name) 
            : '';

        // Student name
        $studentName = trim($student->first_name . ' ' . ($student->middle_name ? $student->middle_name . ' ' : '') . $student->last_name);

        // Class name (combining name and section if available)
        $className = 'N/A';
        if ($student->class) {
            $className = $student->class->name;
            if (!empty($student->class->section)) {
                $className .= ' - ' . $student->class->section;
            }
        }

        return [
            'class' => $className,
            'student name' => $studentName,
            'studentid' => $student->student_id,
            'parent name' => $parentName,
            'dob' => $student->dob,
            'mobile no' => $student->phone ?? $parent->phone ?? '',
            'blood' => $student->blood_group,
            'image' => $student->image, 
        ];
    }
}
