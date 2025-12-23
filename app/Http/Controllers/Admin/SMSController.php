<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\SchoolClass;
use Illuminate\Http\Request;

use App\Services\SMSService;
use App\Models\Admin\Student;
use App\Models\Admin\Teacher;
use App\Models\Admin\ParentModel;

class SMSController extends Controller
{
    protected $sms;

    public function __construct(SMSService $smsService)
    {
        $this->sms = $smsService;
    }

    /**
     * Send SMS to: parents, students, teachers, or all.
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

        $numbers = collect();

        /**
         *  1. Parents
         */
        if ($target === "parents") {

            // Send to all parents
            if (!$request->class_id && !$request->student_ids) {
                $numbers = ParentModel::pluck("phone");
            }

            // Class-wise parents
            if ($request->class_id) {
                $numbers = Student::where("class_id", $request->class_id)
                    ->with("parents")
                    ->get()
                    ->pluck("parents.*.phone")
                    ->flatten()
                    ->unique();
            }

            // Specific student's parent(s)
            if ($request->student_ids) {
                $numbers = Student::whereIn("id", $request->student_ids)
                    ->with("parents")
                    ->get()
                    ->pluck("parents.*.phone")
                    ->flatten()
                    ->unique();
            }
        }

        /**
         *  2. Students
         */
        if ($target === "students") {

            // All students
            if (!$request->class_id && !$request->student_ids) {
                $numbers = Student::pluck("phone");
            }

            // Class-wise students
            if ($request->class_id) {
                $numbers = Student::where("class_id", $request->class_id)
                    ->pluck("phone");
            }

            // Specific student(s)
            if ($request->student_ids) {
                $numbers = Student::whereIn("id", $request->student_ids)
                    ->pluck("phone");
            }
        }

        /**
         *  3. Teachers
         */
        if ($target === "teachers") {

            // All teachers
            if (!$request->teacher_ids) {
                $numbers = Teacher::pluck("phone");
            }

            // Specific teacher(s)
            if ($request->teacher_ids) {
                $numbers = Teacher::whereIn("id", $request->teacher_ids)
                    ->pluck("phone");
            }
        }

        /**
         *  4. Send to ALL (parents + students + teachers)
         */
        if ($target === "all") {
            $numbers = collect()
                ->merge(ParentModel::pluck("phone"))
                ->merge(Student::pluck("phone"))
                ->merge(Teacher::pluck("phone"))
                ->unique();
        }
        /**
         *  Send SMS to all numbers
         */
        foreach ($numbers as $phone) {
            $this->sms->sendSMS($phone, $message);
        }

        return response()->json([
            "status" => true,
            "count" => $numbers->count(),
            "message" => "SMS sent successfully to {$numbers->count()} receivers."
        ]);
    }
    public function sendToTeachers(Request $request, $domain)
    {
        $numbers = Teacher::pluck("phone");
        $message = $request->query('message', 'Default notification message');

        foreach ($numbers as $phone) {
            if ($phone) {
                $this->sms->sendSMS($phone, $message);
            }
        }

        return response()->json([
            "status" => true,
            "count" => $numbers->count(),
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
}
