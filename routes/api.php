<?php

use App\Http\Controllers\Admin\ClassController;
use App\Http\Controllers\Admin\CreateUserController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\EventController;
use App\Http\Controllers\Admin\NoticeController;
use App\Http\Controllers\Admin\ResultController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\StudentController;
use App\Http\Controllers\Admin\SubjectController;
use App\Http\Controllers\Admin\TeacherController;
use App\Http\Controllers\Government\IndividualSchoolStudents;
use App\Http\Controllers\Government\IndividualSchoolTeachers;
use App\Http\Controllers\Government\SchoolController;
use App\Http\Controllers\SuperAdmin\CentralController;
use App\Http\Controllers\SuperAdmin\TenantController;
use Illuminate\Support\Facades\Route;

//? Super Admin routes 
Route::post('/superadmin/register', [CentralController::class, 'register']);
Route::post('/superadmin/login', [CentralController::class, 'login']);
Route::post('/superadmin/logout', [CentralController::class, 'logout'])->middleware('auth:sanctum');
Route::get('/superadmin/users', [CentralController::class, 'viewusers'])->middleware('auth:sanctum');
//? Get all the school Informations
Route::prefix('superadmin/school')->middleware(['auth:sanctum'])->group(function () {
    Route::get('/', [TenantController::class, 'index']);       // List all schools
    Route::post('/', [TenantController::class, 'store']);      // Register a new school
    Route::get('/{tenant}', [TenantController::class, 'show']); // Show single school
    Route::put('/{tenant}', [TenantController::class, 'update']); // Update school
    Route::patch('/{tenant}', [TenantController::class, 'update']); // Partial update
    Route::delete('/{tenant}', [TenantController::class, 'destroy']); // Delete school
});


//? For School Individuals 
// ?To Register, login and logut ROutes for school users
Route::post('/tenants/{domain}/register', [CreateUserController::class, 'register'])->middleware('tenant');
Route::post('/tenants/{domain}/login', [CreateUserController::class, 'login'])->middleware('tenant');
Route::post('/tenants/{domain}/logout', [CreateUserController::class, 'logout'])->middleware('tenant', 'auth:sanctum');

Route::prefix('tenants/{domain}')
    ->middleware(['tenant', 'auth:sanctum'])
    ->group(function () {
        Route::get('/user', [CreateUserController::class, 'show']);
    });


//? Admin Route Whic are only accessable to the admins of the school

// use App\Http\Controllers\Api\SubjectController;

Route::middleware(['tenant', 'auth:sanctum', 'role:admin'])->group(function () {

    // ? To update the settings of the school by admin 
    Route::put('/tenants/{domain}/settings',[SettingController::class, 'update']);


    // ?To create the subjects 
    Route::apiResource('tenants/{domain}/subjects', SubjectController::class);
    

    //? To create the Classes
    Route::apiResource('tenants/{domain}/classes', ClassController::class)->except(['show', 'index']);


    //? To create Teachers
    Route::apiResource('tenants/{domain}/teachers', TeacherController::class)->except('index');

    //? TO create the Students
    Route::apiResource('tenants/{domain}/students', StudentController::class)->except('show', 'store');
    Route::post('tenants/{domain}/students/bulk-upload', [StudentController::class, 'bulkUpload']);

    // ? To Filter the students based on calss and their name
    Route::get('tenants/{domain}/students/class/{classId}', [StudentController::class, 'filterByClass']);
    Route::get('tenants/{domain}/students/class/{classId}/search', [StudentController::class, 'filterByClassAndName']);


    //? To create te Result 
    Route::apiResource('tenants/{domain}/students/results', ResultController::class)->except('store');

    // ? Get the Individual Student Result
    Route::get('tenants/{domain}/students/{studentId}/results', [ResultController::class, 'getStudentResultsById']);

    //? To filter the results of students based in class and the studentId and classId
    Route::get('tenants/{domain}/students/results/class/{classId}', [ResultController::class, 'resultByClass']);
    // Route::get('tenants/{domain}/students/getresults/{studentId}', [ResultController::class, 'studentResult']);
    // routes/api.php
    // Route::get('tenants/{domain}/students/{studentId}/results', [ResultController::class, 'studentResult']);



    // To show the counts in admin panel
    Route::get('tenants/{domain}/admin/dashboard/stats', [DashboardController::class, 'stats']);

    // To manage the Events
    Route::apiResource('tenants/{domain}/events', EventController::class)->except('index', 'show');
   
   
    // To manage the Notices
    Route::apiResource('tenants/{domain}/notices', NoticeController::class)->except('index', 'show');

});

// ?For the Teachers and admin
Route::middleware(['tenant', 'auth:sanctum', 'role:admin,teacher'])->group(function () {
    Route::post('tenants/{domain}/teachers/me', [TeacherController::class, 'me']);

    Route::get('tenants/{domain}/classes/{id}', [ClassController::class, 'show']);
    Route::get('tenants/{domain}/classes', [ClassController::class, 'index']);


    Route::post('tenants/{domain}/students', [StudentController::class, 'store']);
    Route::get('tenants/{domain}/students/class/{classId}', [StudentController::class, 'filterByClass']);
    Route::post('tenants/{domain}/students/results', [ResultController::class, 'store']);

    
    Route::post('tenants/{domain}/students/results/create', [ResultController::class, 'createResultByTeacher']);

    Route::get('tenants/{domain}/classes/{classId}/subjects', [SubjectController::class, 'getSubjectsByClass']);
    Route::post('tenants/{domain}/class-subjects', [SubjectController::class, 'storeClassSubjectTeacher']);


    //? To view the result for the teachers 
    Route::get('tenants/{domain}/teachers/{teacherId}/results', [ResultController::class, 'resultsByTeacher']);

    Route::get('tenants/{domain}/results/{classId}', [ResultController::class, 'classLedger']);


    // ? Bulk Upload
    Route::post('tenants/{domain}/students/bulk-upload', [StudentController::class, 'bulkUpload']);
    Route::post('tenants/{domain}/students/results/bulk-upload', [ResultController::class, 'bulkStore']);

    
});


// ?For the students and parents
Route::middleware(['tenant', 'auth:sanctum', 'role:student,parent,admin,teacher'])->group(function () {
    Route::post('tenants/{domain}/students/profile', [StudentController::class,'profile']);
    Route::get('tenants/{domain}/students/{id}', [StudentController::class,'show']);


    Route::get('tenants/{domain}/students/result', [ResultController::class, 'studentResult']);



    // To get the events
    Route::get('tenants/{domain}/events', [EventController::class, 'index']);
    Route::get('tenants/{domain}/admin/events/{id}', [EventController::class, 'show']);

});



// No need to login Routes
Route::middleware(['tenant'])->group(function () {


    // ?To get the Details of the school (Settings)
    Route::get('/tenants/{domain}/settings',[SettingController::class, 'index']);
    
    // To get the events
    Route::get('tenants/{domain}/events', [EventController::class, 'index']);
    Route::get('tenants/{domain}/events/{id}', [EventController::class, 'show']);
    // To get the notices
    Route::get('tenants/{domain}/notices', [NoticeController::class, 'index']);
    Route::get('tenants/{domain}/notices/{id}', [NoticeController::class, 'show']);



    Route::get('tenants/{domain}/teachers', action: [TeacherController::class, 'index']);
    // Route::get('tenants/{domain}/teachers/{id}', action: [TeacherController::class, 'show']);
    
    
});



// ? Apis which needs everywhere access
Route::get('local-bodies/districts', [App\Http\Controllers\SuperAdmin\LocalBodiesController::class, 'getAllDistricts']);
Route::get('local-bodies/{district}', [App\Http\Controllers\SuperAdmin\LocalBodiesController::class, 'getByDistrict']);



// ?For the Government School Controller
Route::get('schools/by-local-unit/{localUnit}', [App\Http\Controllers\Government\SchoolController::class, 'getSchoolsByLocalUnit']);
Route::get('schools/by-local-unit/{localUnit}/{ward}', [App\Http\Controllers\Government\SchoolController::class, 'getSchoolsByLocalUnitWard']);
Route::get('school/details/{id}',[SchoolController::class, 'showSchool']);


//? To get the Individual Schools Teachers  List |
Route::get("/school/teachers/{id}",[IndividualSchoolTeachers::class, 'getAllTeachers']);

// ? To get the Individual Schools Students List |
Route::get("/school/students/{id}",[IndividualSchoolStudents::class, 'getAllStudents']);

//? to get the Individual Student details for the government
Route::get("/school/students/{id}/{studentId}",[IndividualSchoolStudents::class, 'getIndividualStudentDetails']);

