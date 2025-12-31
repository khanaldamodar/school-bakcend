<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\Attendance;
use App\Models\Admin\Student;
use App\Models\Admin\Teacher;
use App\Models\Admin\AcademicYear;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\TenantLogger;

class AttendanceController extends Controller
{
    /**
     * Display a listing of attendance.
     */
    public function index(Request $request, $domain)
    {
        $query = Attendance::with(['student', 'teacher', 'schoolClass']);

        if ($request->has('class_id') && $request->class_id != '') {
            $query->where('class_id', $request->class_id);
        }

        if ($request->has('student_id') && $request->student_id != '') {
            $query->where('student_id', $request->student_id);
        }

        if ($request->has('teacher_id') && $request->teacher_id != '') {
            $query->where('teacher_id', $request->teacher_id);
        }

        if ($request->has('date') && $request->date != '') {
            $query->where('attendance_date', $request->date);
        }

        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('attendance_date', [$request->start_date, $request->end_date]);
        }

        if ($request->has('type')) {
            if ($request->type === 'student') {
                $query->whereNotNull('student_id');
            } elseif ($request->type === 'teacher') {
                $query->whereNotNull('teacher_id');
            }
        }

        $attendances = $query->orderBy('attendance_date', 'desc')
                            ->orderBy('created_at', 'desc')
                            ->paginate($request->get('per_page', 50));

        return response()->json([
            'status' => true,
            'message' => 'Attendances fetched successfully',
            'data' => $attendances->items(),
            'meta' => [
                'current_page' => $attendances->currentPage(),
                'last_page' => $attendances->lastPage(),
                'per_page' => $attendances->perPage(),
                'total' => $attendances->total(),
            ],
        ]);
    }

    /**
     * Store a newly created attendance record.
     */
    public function store(Request $request, $domain)
    {
        $validated = $request->validate([
            'student_id' => 'nullable|exists:students,id',
            'teacher_id' => 'nullable|exists:teachers,id',
            'class_id' => 'nullable|exists:classes,id',
            'attendance_date' => 'required|date',
            'check_in' => 'nullable',
            'check_out' => 'nullable',
            'status' => 'required|in:present,absent,late,half_day,on_leave',
            'source' => 'nullable|string',
            'device_id' => 'nullable|string',
            'device_user_id' => 'nullable|string',
            'remarks' => 'nullable|string',
        ]);

        if (empty($validated['student_id']) && empty($validated['teacher_id'])) {
            return response()->json([
                'status' => false,
                'message' => 'Either student_id or teacher_id is required',
            ], 422);
        }

        // Get current academic year
        $currentYear = AcademicYear::where('is_current', true)->first();
        $validated['academic_year_id'] = $currentYear ? $currentYear->id : null;

        // If student_id is provided but class_id is not, try to get it from student
        if (!empty($validated['student_id']) && empty($validated['class_id'])) {
            $student = Student::find($validated['student_id']);
            if ($student) {
                $validated['class_id'] = $student->class_id;
            }
        }

        $attendance = Attendance::updateOrCreate(
            [
                'student_id' => $validated['student_id'] ?? null,
                'teacher_id' => $validated['teacher_id'] ?? null,
                'attendance_date' => $validated['attendance_date'],
            ],
            $validated
        );

        TenantLogger::logCreate('attendance', "Attendance recorded for " . ($attendance->student_id ? "Student ID: " . $attendance->student_id : "Teacher ID: " . $attendance->teacher_id), ['id' => $attendance->id]);

        return response()->json([
            'status' => true,
            'message' => 'Attendance recorded successfully',
            'data' => $attendance,
        ], 201);
    }

    /**
     * Bulk store attendance (useful for device sync).
     */
    public function bulkStore(Request $request, $domain)
    {
        $request->validate([
            'attendances' => 'required|array|min:1',
            'attendances.*.student_id' => 'nullable',
            'attendances.*.teacher_id' => 'nullable',
            'attendances.*.attendance_date' => 'required|date',
            'attendances.*.check_in' => 'nullable',
            'attendances.*.check_out' => 'nullable',
            'attendances.*.status' => 'required|in:present,absent,late,half_day,on_leave',
        ]);

        $currentYear = AcademicYear::where('is_current', true)->first();
        $academicYearId = $currentYear ? $currentYear->id : null;

        $results = [
            'success' => 0,
            'failed' => 0,
            'errors' => []
        ];

        DB::beginTransaction();
        try {
            foreach ($request->attendances as $index => $data) {
                $studentId = $data['student_id'] ?? null;
                $teacherId = $data['teacher_id'] ?? null;
                
                if (!$studentId && !$teacherId) {
                    $results['failed']++;
                    $results['errors'][] = "Row $index: Missing student or teacher ID";
                    continue;
                }

                $classId = $data['class_id'] ?? null;
                if ($studentId && !$classId) {
                    $student = Student::find($studentId);
                    if ($student) $classId = $student->class_id;
                }

                Attendance::updateOrCreate(
                    [
                        'student_id' => $studentId,
                        'teacher_id' => $teacherId,
                        'attendance_date' => $data['attendance_date'],
                    ],
                    array_merge($data, [
                        'academic_year_id' => $academicYearId,
                        'class_id' => $classId,
                        'source' => $data['source'] ?? 'device'
                    ])
                );
                $results['success']++;
            }
            DB::commit();
            TenantLogger::logCreate('attendance', "Bulk attendance processed: {$results['success']} records", ['success_count' => $results['success']]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => 'Bulk attendance storage failed',
                'error' => $e->getMessage()
            ], 500);
        }

        return response()->json([
            'status' => true,
            'message' => 'Bulk attendance processed',
            'results' => $results
        ]);
    }

    /**
     * Get attendance for all students in a specific class for a given date.
     * This helps in identifying who is present/absent in a class view.
     */
    public function classAttendance(Request $request, $domain, $classId)
    {
        $date = $request->get('date', now()->format('Y-m-d'));
        
        // Get all students in the class
        $students = Student::where('class_id', $classId)
            ->select('id', 'first_name', 'middle_name', 'last_name', 'roll_number', 'image')
            ->get();

        // Get attendance records for these students on the given date
        $attendances = Attendance::where('class_id', $classId)
            ->whereDate('attendance_date', $date)
            ->get()
            ->keyBy('student_id');

        // Merge attendance data into student objects
        $data = $students->map(function ($student) use ($attendances) {
            $attendance = $attendances->get($student->id);
            return [
                'student' => $student,
                'attendance' => $attendance ?? [
                    'status' => 'not_marked',
                    'check_in' => null,
                    'check_out' => null,
                ]
            ];
        });

        return response()->json([
            'status' => true,
            'message' => 'Class attendance report fetched successfully',
            'date' => $date,
            'data' => $data,
        ]);
    }

    /**
     * Get attendance for the authenticated user.
     */
    public function myAttendance(Request $request, $domain)
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json(['status' => false, 'message' => 'Unauthenticated'], 401);
        }

        $query = Attendance::query();

        if ($user->role === 'student') {
            $student = Student::where('user_id', $user->id)->first();
            if (!$student) return response()->json(['status' => false, 'message' => 'Student record not found'], 404);
            $query->where('student_id', $student->id);
        } elseif ($user->role === 'teacher') {
            $teacher = Teacher::where('user_id', $user->id)->first();
            if (!$teacher) return response()->json(['status' => false, 'message' => 'Teacher record not found'], 404);
            $query->where('teacher_id', $teacher->id);
        } elseif ($user->role === 'admin' || $user->role === 'superadmin') {
            // Admin can see everything, or maybe they should use index()
            return $this->index($request, $domain);
        } else {
            return response()->json(['status' => false, 'message' => 'Not authorized'], 403);
        }

        $attendances = $query->with(['schoolClass'])
                            ->orderBy('attendance_date', 'desc')
                            ->paginate($request->get('per_page', 30));

        return response()->json([
            'status' => true,
            'message' => 'My attendance fetched successfully',
            'data' => $attendances->items(),
            'meta' => [
                'current_page' => $attendances->currentPage(),
                'last_page' => $attendances->lastPage(),
                'per_page' => $attendances->perPage(),
                'total' => $attendances->total(),
            ],
        ]);
    }
}
