<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\AcademicYear;
use App\Models\Admin\SMSMessage;
use App\Models\Admin\SchoolClass;
use Illuminate\Http\Request;

use App\Services\SMSService;
use App\Models\Admin\Student;
use App\Models\Admin\Teacher;
use App\Models\Admin\ParentModel;
use App\Services\TenantLogger;

class SMSController extends Controller
{
    protected $sms;

    public function __construct(SMSService $smsService)
    {
        $this->sms = $smsService;
    }

    /**
     * Send SMS to: parents, students, teachers, or all.
     *
     * - Respects central tenant SMS balance.
     * - Stores per-recipient SMS history.
     * - Avoids sending duplicate messages to the same phone number.
     */
    public function send(Request $request, $domain)
    {
        $request->validate([
            "target" => "required|in:parents,students,teachers,all",
            "message" => "required|string|max:160",

            // optional filters
            "class_id" => "nullable|integer",
            "student_ids" => "nullable|array",
            "student_ids.*" => "integer",

            "teacher_ids" => "nullable|array",
            "teacher_ids.*" => "integer"
        ]);

        $target = $request->target;
        $message = $request->message;

        // Build a list of recipients with IDs & models for history
        $recipients = collect();

        /**
         *  1. Parents
         */
        if ($target === "parents") {

            // Send to all parents
            if (!$request->class_id && !$request->student_ids) {
                $recipients = ParentModel::select('id', 'phone')
                    ->get()
                    ->map(function ($parent) {
                        return [
                            'phone' => $parent->phone,
                            'recipient_model' => ParentModel::class,
                            'recipient_id' => $parent->id,
                            'recipient_type' => 'parent',
                        ];
                    });
            }

            // Class-wise parents
            if ($request->class_id) {
                $recipients = Student::where("class_id", $request->class_id)
                    ->with("parents")
                    ->get()
                    ->flatMap(function ($student) {
                        return $student->parents->map(function ($parent) {
                            return [
                                'phone' => $parent->phone,
                                'recipient_model' => ParentModel::class,
                                'recipient_id' => $parent->id,
                                'recipient_type' => 'parent',
                            ];
                        });
                    });
            }

            // Specific student's parent(s)
            if ($request->student_ids) {
                $recipients = Student::whereIn("id", $request->student_ids)
                    ->with("parents")
                    ->get()
                    ->flatMap(function ($student) {
                        return $student->parents->map(function ($parent) {
                            return [
                                'phone' => $parent->phone,
                                'recipient_model' => ParentModel::class,
                                'recipient_id' => $parent->id,
                                'recipient_type' => 'parent',
                            ];
                        });
                    });
            }
        }

        /**
         *  2. Students
         */
        if ($target === "students") {

            // All students
            if (!$request->class_id && !$request->student_ids) {
                $recipients = Student::select('id', 'phone')
                    ->get()
                    ->map(function ($student) {
                        return [
                            'phone' => $student->phone,
                            'recipient_model' => Student::class,
                            'recipient_id' => $student->id,
                            'recipient_type' => 'student',
                        ];
                    });
            }

            // Class-wise students
            if ($request->class_id) {
                $recipients = Student::where("class_id", $request->class_id)
                    ->select('id', 'phone')
                    ->get()
                    ->map(function ($student) {
                        return [
                            'phone' => $student->phone,
                            'recipient_model' => Student::class,
                            'recipient_id' => $student->id,
                            'recipient_type' => 'student',
                        ];
                    });
            }

            // Specific student(s)
            if ($request->student_ids) {
                $recipients = Student::whereIn("id", $request->student_ids)
                    ->select('id', 'phone')
                    ->get()
                    ->map(function ($student) {
                        return [
                            'phone' => $student->phone,
                            'recipient_model' => Student::class,
                            'recipient_id' => $student->id,
                            'recipient_type' => 'student',
                        ];
                    });
            }
        }

        /**
         *  3. Teachers
         */
        if ($target === "teachers") {

            // All teachers
            if (!$request->teacher_ids) {
                $recipients = Teacher::select('id', 'phone')
                    ->get()
                    ->map(function ($teacher) {
                        return [
                            'phone' => $teacher->phone,
                            'recipient_model' => Teacher::class,
                            'recipient_id' => $teacher->id,
                            'recipient_type' => 'teacher',
                        ];
                    });
            }

            // Specific teacher(s)
            if ($request->teacher_ids) {
                $recipients = Teacher::whereIn("id", $request->teacher_ids)
                    ->select('id', 'phone')
                    ->get()
                    ->map(function ($teacher) {
                        return [
                            'phone' => $teacher->phone,
                            'recipient_model' => Teacher::class,
                            'recipient_id' => $teacher->id,
                            'recipient_type' => 'teacher',
                        ];
                    });
            }
        }

        /**
         *  4. Send to ALL (parents + students + teachers)
         */
        if ($target === "all") {
            $allParents = ParentModel::select('id', 'phone')
                ->get()
                ->map(function ($parent) {
                    return [
                        'phone' => $parent->phone,
                        'recipient_model' => ParentModel::class,
                        'recipient_id' => $parent->id,
                        'recipient_type' => 'parent',
                    ];
                });

            $allStudents = Student::select('id', 'phone')
                ->get()
                ->map(function ($student) {
                    return [
                        'phone' => $student->phone,
                        'recipient_model' => Student::class,
                        'recipient_id' => $student->id,
                        'recipient_type' => 'student',
                    ];
                });

            $allTeachers = Teacher::select('id', 'phone')
                ->get()
                ->map(function ($teacher) {
                    return [
                        'phone' => $teacher->phone,
                        'recipient_model' => Teacher::class,
                        'recipient_id' => $teacher->id,
                        'recipient_type' => 'teacher',
                    ];
                });

            $recipients = collect()
                ->merge($allParents)
                ->merge($allStudents)
                ->merge($allTeachers);
        }

        // Filter invalid numbers and ensure no duplicate phone numbers
        $recipients = $recipients
            ->filter(function ($item) {
                return !empty($item['phone']);
            })
            ->unique('phone')
            ->values();

        $count = $recipients->count();

        if ($count === 0) {
            return response()->json([
                "status" => false,
                "message" => "No valid phone numbers found for the selected target.",
            ], 422);
        }

        // Check SMS balance from central tenant
        $tenant = tenant();
        if ($tenant->sms_balance < $count) {
            return response()->json([
                'status' => false,
                'message' => 'Insufficient SMS balance',
                'required' => $count,
                'available' => $tenant->sms_balance,
            ], 422);
        }

        $currentYear = AcademicYear::current();
        $sender = $request->user();
        $sentAt = now();

        foreach ($recipients as $recipient) {
            $phone = $recipient['phone'];

            $this->sms->sendSMS($phone, $message);

            SMSMessage::create([
                'academic_year_id' => $currentYear?->id,
                'event_id' => null,
                'sender_id' => $sender?->id,
                'sender_role' => $sender?->role ?? null,
                'target_group' => $target,
                'recipient_phone' => $phone,
                'recipient_model' => $recipient['recipient_model'] ?? null,
                'recipient_id' => $recipient['recipient_id'] ?? null,
                'message' => $message,
                'status' => 'sent',
                'sent_at' => $sentAt,
            ]);
        }

        // Deduct used SMS from central tenant balance
        $tenant->sms_balance = max(0, $tenant->sms_balance - $count);
        $tenant->save();

        TenantLogger::tenant('info', "Sent bulk SMS to {$target}", [
            'count' => $count,
            'target' => $target
        ]);

        return response()->json([
            "status" => true,
            "count" => $count,
            "remaining_balance" => $tenant->sms_balance,
            "message" => "SMS sent successfully to {$count} receivers."
        ]);
    }
    public function sendToTeachers(Request $request, $domain)
    {
        $numbers = Teacher::pluck("phone")->filter()->unique();
        $message = $request->query('message', 'Default notification message');

        // Check central tenant balance before sending
        $tenant = tenant();
        $count = $numbers->count();
        if ($count === 0) {
            return response()->json([
                "status" => false,
                "message" => "No valid teacher phone numbers found.",
            ], 422);
        }

        if ($tenant->sms_balance < $count) {
            return response()->json([
                'status' => false,
                'message' => 'Insufficient SMS balance',
                'required' => $count,
                'available' => $tenant->sms_balance,
            ], 422);
        }

        $currentYear = AcademicYear::current();
        $sender = $request->user();
        $sentAt = now();

        foreach ($numbers as $phone) {
            if ($phone) {
                $this->sms->sendSMS($phone, $message);

                SMSMessage::create([
                    'academic_year_id' => $currentYear?->id,
                    'event_id' => null,
                    'sender_id' => $sender?->id,
                    'sender_role' => $sender?->role ?? null,
                    'target_group' => 'teachers',
                    'recipient_phone' => $phone,
                    'recipient_model' => Teacher::class,
                    'recipient_id' => null,
                    'message' => $message,
                    'status' => 'sent',
                    'sent_at' => $sentAt,
                ]);
            }
        }

        $tenant->sms_balance = max(0, $tenant->sms_balance - $count);
        $tenant->save();

        return response()->json([
            "status" => true,
            "count" => $numbers->count(),
            "remaining_balance" => $tenant->sms_balance,
            "message" => "SMS sent successfully to teachers."
        ]);
    }

    public function getClass(Request $request, $domain)
    {
        $classes = SchoolClass::select('id', 'name')->get();

        if ($classes->isEmpty()) {
            return response()->json([
                "status" => false,
                "message" => "No Class Found"
            ], 404);
        }

        return response()->json([
            "status" => true,
            "message" => "Class Fetched Successfully",
            "data" => $classes
        ], 200);
    }

    /**
     * Get SMS usage for the current tenant (school).
     *
     * Returns remaining balance from central DB and total messages sent,
     * optionally filtered by academic year.
     */
    public function usage(Request $request, $domain)
    {
        $tenant = tenant();

        $query = SMSMessage::query();

        if ($request->filled('academic_year_id')) {
            $query->where('academic_year_id', $request->academic_year_id);
        }

        $totalSent = $query->count();

        return response()->json([
            'status' => true,
            'data' => [
                'sms_balance' => $tenant->sms_balance,
                'total_sent' => $totalSent,
                'academic_year_id' => $request->academic_year_id ?? null,
            ],
        ], 200);
    }
}
