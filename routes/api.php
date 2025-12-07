<?php
use App\Http\Controllers\Admin\ClassController;
use App\Http\Controllers\Admin\CreateUserController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\EventController;
use App\Http\Controllers\Admin\ExtraCurricularActivityController;
use App\Http\Controllers\Admin\NoticeController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\ResultController;
use App\Http\Controllers\Admin\ResultSettingController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\SMSController;
use App\Http\Controllers\Admin\StudentController;
use App\Http\Controllers\Admin\SubjectController;
use App\Http\Controllers\Admin\TeacherController;
use App\Http\Controllers\Government\AllTeachersController;
use App\Http\Controllers\Government\AnalyticsController;
use App\Http\Controllers\Government\GovernmentController;
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


Route::middleware(['tenant', 'auth:sanctum', 'role:student,parent,admin,teacher'])->group(function () {
    Route::get('tenants/{domain}/students/result', [ResultController::class, 'studentResult']);
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
    Route::put('/tenants/{domain}/settings', [SettingController::class, 'update']);
    Route::post('/tenants/{domain}/result-settings', [ResultSettingController::class, 'store']);
    Route::put('/tenants/{domain}/result-settings/{id}', [ResultSettingController::class, 'update']);


    // ?To create the subjects 
    Route::apiResource('tenants/{domain}/subjects', SubjectController::class);
    Route::post('tenants/{domain}/extra-curricular', [ExtraCurricularActivityController::class, 'store']);
    Route::put('tenants/{domain}/extra-curricular/{curricularId}', [ExtraCurricularActivityController::class, 'update']);
    

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
    
    // Get full result for a specific student (Admin/Teacher)
    // Route::get('tenants/{domain}/students/{studentId}/full-result', [ResultController::class, 'studentResult']);



    // To show the counts in admin panel
    Route::get('tenants/{domain}/admin/dashboard/stats', [DashboardController::class, 'stats']);

    // To manage the Events
    Route::apiResource('tenants/{domain}/events', EventController::class)->except('index', 'show');


    // To manage the Notices
    Route::apiResource('tenants/{domain}/notices', NoticeController::class)->except('index', 'show');

    // To get te class While Sending the message. 
    Route::get('tenants/{domain}/sms-class', [SMSController::class, 'getClass']);
});

// ?For the Teachers and admin
Route::middleware(['tenant', 'auth:sanctum', 'role:admin,teacher'])->group(function () {
    Route::post('tenants/{domain}/teachers/me', [TeacherController::class, 'me']);
    Route::get('tenants/{domain}/result-settings', [ResultSettingController::class, 'index']);

    Route::get('tenants/{domain}/classes/{id}', [ClassController::class, 'show']);
    Route::get('tenants/{domain}/classes', [ClassController::class, 'index']);


    Route::post('tenants/{domain}/students', [StudentController::class, 'store']);
    Route::get('tenants/{domain}/students/class/{classId}', [StudentController::class, 'filterByClass']);
    Route::post('tenants/{domain}/students/results', [ResultController::class, 'store']);


    Route::post('tenants/{domain}/students/results/create', [ResultController::class, 'createResultByTeacher']);
    // To get the data of result
 Route::post('tenants/{domain}/results/class', [ResultController::class, 'createClassResultByTeacher']);
 Route::get('tenants/{domain}/classes/{classId}/results', [ResultController::class, 'getWholeClassResults']);




    Route::get('tenants/{domain}/classes/{classId}/subjects', [SubjectController::class, 'getSubjectsByClass']);
    Route::post('tenants/{domain}/class-subjects', [SubjectController::class, 'storeClassSubjectTeacher']);
    Route::get('tenants/{domain}/class-subjects-teacher', [SubjectController::class, 'getClassSubjectTeacher']);


    //? To view the result for the teachers 
    Route::get('tenants/{domain}/teachers/{teacherId}/results', [ResultController::class, 'resultsByTeacher']);

    Route::get('tenants/{domain}/results/{classId}', [ResultController::class, 'classLedger']);


    // ? Bulk Upload
    Route::post('tenants/{domain}/students/bulk-upload', [StudentController::class, 'bulkUpload']);
    Route::post('tenants/{domain}/students/results/bulk-upload', [ResultController::class, 'bulkStore']);


});


// ?For the students and parents
Route::middleware(['tenant', 'auth:sanctum', 'role:student,parent,admin,teacher'])->group(function () {
    Route::post('tenants/{domain}/students/profile', [StudentController::class, 'profile']);
    Route::get('tenants/{domain}/students/{id}', [StudentController::class, 'show']);


    Route::get('tenants/{domain}/students/result', [ResultController::class, 'studentResult']);



    // To get the events
    Route::get('tenants/{domain}/events', [EventController::class, 'index']);
    Route::get('tenants/{domain}/admin/events/{id}', [EventController::class, 'show']);

});



// No need to login Routes
Route::middleware(['tenant'])->group(function () {
    // ?To get the Details of the school (Settings)
    Route::get('/tenants/{domain}/settings', [SettingController::class, 'index']);

    // To get the events
    Route::get('tenants/{domain}/events', [EventController::class, 'index']);
    Route::get('tenants/{domain}/events/{id}', [EventController::class, 'show']);
    // To get the notices
    Route::get('tenants/{domain}/notices', [NoticeController::class, 'index']);
    Route::get('tenants/{domain}/notices/{id}', [NoticeController::class, 'show']);
    Route::get('tenants/{domain}/teachers', action: [TeacherController::class, 'index']);
    //? Route::get('tenants/{domain}/teachers/{id}', action: [TeacherController::class, 'show']);


    //? Report api for school
    Route::get('tenants/{domain}/reports', [ReportController::class, 'getReports']);
    Route::post('tenants/{domain}/send-sms', [SMSController::class, 'send']);
    Route::get('tenants/{domain}/send-sms-teachers', [SMSController::class, 'sendToTeachers']);
});

// ?Register New Government account
Route::post("/auth/gov", [GovernmentController::class, 'register']);
Route::post("/auth/gov/login", [GovernmentController::class, 'login']);



// ? Apis which needs everywhere access
Route::get('local-bodies/districts', [App\Http\Controllers\SuperAdmin\LocalBodiesController::class, 'getAllDistricts']);
Route::get('local-bodies/{district}', [App\Http\Controllers\SuperAdmin\LocalBodiesController::class, 'getByDistrict']);



// ?For the Government School Controller
Route::middleware(['auth:sanctum', 'role:government'])->group(function () {
    Route::get('schools/by-local-unit/{localUnit}', [App\Http\Controllers\Government\SchoolController::class, 'getSchoolsByLocalUnit']);
    Route::get('schools/by-local-unit/{localUnit}/{ward}', [App\Http\Controllers\Government\SchoolController::class, 'getSchoolsByLocalUnitWard']);
    Route::get('school/details/{id}', [SchoolController::class, 'showSchool']);
    //? To get the Individual Schools Teachers  List |
    Route::get("/school/{schoolId}/teachers", [IndividualSchoolTeachers::class, 'getAllTeachers']);
    // ? Individual Teacher section for Teacher Details
    Route::get("/school/{schoolId}/teacher/{teacherId}", [IndividualSchoolTeachers::class, 'getTeacherDetails']);
    // ? To get the Individual Schools Students List |
    Route::get("/school/{schoolId}/students", [IndividualSchoolStudents::class, 'getAllStudents']);
    //? to get the Individual Student details for the government
    Route::get("/school/{SchoolId}/students/{studentId}", [IndividualSchoolStudents::class, 'getIndividualStudentDetails']);
    Route::get('/school/{schoolId}/students/{studentId}/result', [IndividualSchoolStudents::class, 'getIndividualStudentResult']);
    // ? for the filters
    Route::get('/single-school/{schoolId}/{isTribe?}/{isDisable?}/{gender?}', [AnalyticsController::class, 'singleSchool']);
    Route::get('students/filter', [AnalyticsController::class, 'filterStudents']);
    Route::get('/teachers/filter', [AnalyticsController::class, 'filterTeachers']);
    Route::get("/multiple-school/{school1}/{school2}", [AnalyticsController::class, "multipleSchool"]);
    // All in One
    Route::post('/gov/analytics', [AnalyticsController::class, 'filter']);
    Route::post('/gov/analytics/singleschool', [AnalyticsController::class, 'singleSchoolStudentFilter']);
    // Get all the Teachers of thee Nagarpalica
    Route::get('/gov/teachers/{localUnit}', [AllTeachersController::class, "getAllTeachers"]);

});



