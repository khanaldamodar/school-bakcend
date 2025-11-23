<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\Student;
use Illuminate\Http\Request;

class ReportController extends Controller
{

    public function getReports($classId, $isTribe, $isDisabled)
    {
        // Get all Students
        $students = Student::all()->count();
        // Male 
        $male = Student::where('gender', "male")->count();
        $female = Student::where('gender', "female")->count();
        $other = Student::where('gender', "other")->count();
    }

}
