<?php
use App\Http\Controllers\Admin\AnalyticalReportController;
use App\Http\Controllers\Admin\AttendanceController;

use App\Http\Controllers\Admin\ContactController;
use App\Http\Controllers\Admin\EventTypeController;
use App\Http\Controllers\Admin\IdentityCardController;
use App\Http\Controllers\Admin\QuickLinkContrtoller;
use App\Http\Controllers\Admin\VoiceController;
use App\Http\Controllers\SuperAdmin\TenantController;
use App\Http\Controllers\Admin\ClassController;
use App\Http\Controllers\Admin\ClubController;
use App\Http\Controllers\Admin\CreateUserController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\EventController;
use App\Http\Controllers\Admin\ExtraCurricularActivityController;
use App\Http\Controllers\Admin\GalleryController;
use App\Http\Controllers\Admin\NoticeController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\ResultController;
use App\Http\Controllers\Admin\ResultSettingController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\SMSController;
use App\Http\Controllers\Admin\SMSSettingController;
use App\Http\Controllers\Admin\StudentClubController;
use App\Http\Controllers\Admin\StudentController;
use App\Http\Controllers\Admin\SubjectController;
use App\Http\Controllers\Admin\TeacherController;
use App\Http\Controllers\Admin\AcademicYearController;
use App\Http\Controllers\Admin\StudentPromotionController;
use App\Http\Controllers\Admin\TeacherRoleController;
use App\Http\Controllers\Admin\WebsiteSettingController;
use App\Http\Controllers\Admin\PostController;
use App\Http\Controllers\Government\AllTeachersController;
use App\Http\Controllers\Government\AnalyticsController;
use App\Http\Controllers\Government\GovernmentController;
use App\Http\Controllers\Government\IndividualSchoolStudents;
use App\Http\Controllers\Government\IndividualSchoolTeachers;
use App\Http\Controllers\Government\SchoolController;
use App\Http\Controllers\SuperAdmin\CentralController;
use App\Http\Controllers\SuperAdmin\SystemLogController;
use App\Http\Controllers\SuperAdmin\SmsController as SuperAdminSmsController;
use App\Http\Controllers\SuperAdmin\SchoolDataController;
use App\Http\Controllers\SuperAdmin\LocalBodiesController;
use App\Http\Controllers\Admin\FinalResultController;
use Illuminate\Support\Facades\Route;

//? Super Admin routes 
Route::post('/superadmin/register', [CentralController::class, 'register']);
Route::post('/superadmin/login', [CentralController::class, 'login']);
Route::post('/superadmin/logout', [CentralController::class, 'logout'])->middleware('auth:sanctum');
Route::get('/superadmin/users', [CentralController::class, 'viewusers'])->middleware('auth:sanctum');

// Super Admin Stats
Route::get('/superadmin/stats', [SchoolDataController::class, 'getStats'])->middleware('auth:sanctum');

//? Get all the school Informations
Route::prefix('superadmin/school')->middleware(['auth:sanctum'])->group(function () {
    Route::get('/', [TenantController::class, 'index']);       // List all schools
    Route::post('/', [TenantController::class, 'store']);      // Register a new school
    Route::get('/{tenant}', [TenantController::class, 'show']); // Show single school
    Route::put('/{tenant}', [TenantController::class, 'update']); // Update school
    Route::patch('/{tenant}', [TenantController::class, 'update']); // Partial update
    Route::delete('/{tenant}', [TenantController::class, 'destroy']); // Delete school

    // SMS Balance routes
    Route::get('/{tenant}/sms-balance', [SuperAdminSmsController::class, 'getBalance']);
    Route::post('/{tenant}/add-sms', [SuperAdminSmsController::class, 'addBalance']);

    // School Data routes (Deleted records)
    Route::get('/{tenant}/deleted-students', [SchoolDataController::class, 'getDeletedStudents']);
    Route::get('/{tenant}/deleted-teachers', [SchoolDataController::class, 'getDeletedTeachers']);
});

//? System Logs for Super Admin
Route::prefix('superadmin/logs')->middleware(['auth:sanctum'])->group(function () {
    Route::get('/', [SystemLogController::class, 'index']);
    Route::get('/{id}', [SystemLogController::class, 'show']);
});

//? Local Bodies for Super Admin
Route::prefix('superadmin/local-bodies')->middleware(['auth:sanctum'])->group(function () {
    Route::get('/', function () {
        return response()->json([
            'status' => true,
            'data' => \App\Models\LocalBody::all()
        ]);
    });
});



Route::middleware(['tenant', 'auth:sanctum', 'role:student,parent,admin,teacher'])->group(function () {
    Route::get('tenants/{domain}/students/result', [ResultController::class, 'studentResult']);
});


//? For School Individuals 
// ?To Register, login and logut ROutes for school users
Route::post('/tenants/{domain}/register', [CreateUserController::class, 'register'])->middleware('tenant');
Route::post('/tenants/{domain}/login', [CreateUserController::class, 'login'])->middleware(['rate.limit.login', 'tenant']);
Route::post('/tenants/{domain}/logout', [CreateUserController::class, 'logout'])->middleware('tenant', 'auth:sanctum');

Route::prefix('tenants/{domain}')
    ->middleware(['tenant', 'auth:sanctum'])
    ->group(function () {
        Route::get('/user', [CreateUserController::class, 'show']);
    });


//? Admin Route Whic are only accessable to the admins of the school

// use App\Http\Controllers\Api\SubjectController;

Route::middleware(['tenant', 'auth:sanctum', 'role:admin'])->group(function () {

    // ? Identity Cards
    Route::get('tenants/{domain}/identity-cards/bulk', [IdentityCardController::class, 'bulkPrint']);
    Route::get('tenants/{domain}/identity-cards/{id}', [IdentityCardController::class, 'individualPrint']);

    // ? Contacts
    Route::apiResource("/tenants/{domain}/contacts", ContactController::class)->except(['store']);


    // ? Quick Links
    Route::apiResource("/tenants/{domain}/quick-links", QuickLinkContrtoller::class)->except(['index', 'show']);

    // ? For Creating the Clubs
    Route::apiResource("/tenants/{domain}/clubs", ClubController::class)->except('index', 'show');

    // ? For adding students to the clubs.

    Route::post("/tenants/{domain}/clubs/students", [StudentClubController::class, 'store']);
    Route::put("/tenants/{domain}/clubs/{id}/students", [StudentClubController::class, 'update']);
    Route::delete("/tenants/{domain}/clubs/{id}/students", [StudentClubController::class, 'destroy']);


    // ? To update the settings of the school by admin 
    Route::put('/tenants/{domain}/settings', [SettingController::class, 'update']);
    Route::post('/tenants/{domain}/result-settings', [ResultSettingController::class, 'store']);
    Route::put('/tenants/{domain}/result-settings/{id}', [ResultSettingController::class, 'update']);
    Route::delete('/tenants/{domain}/result-settings/{id}', [ResultSettingController::class, 'destroy']);



    // ? Update Website Settings
    Route::put('tenants/{domain}/website-settings/{id}', [WebsiteSettingController::class, 'update']);
    Route::post('tenants/{domain}/website-settings', [WebsiteSettingController::class, 'store']);


    // ? For adding School Members Mainly for staffs
    Route::apiResource('tenants/{domain}/school-members', App\Http\Controllers\Admin\SchoolMemberController::class)->except(['index', 'show']);




    // // ? For Website  Settings 
    // Route::get('tenants/{domain}/website-settings', [WebsiteSettingController::class, 'index']);
    // Route::apiResource('tenants/{domain}/website-settings', WebsiteSettingController::class);


    // ?To create the subjects 
    Route::apiResource('tenants/{domain}/subjects', SubjectController::class);
    Route::post('tenants/{domain}/extra-curricular/bulk', [ExtraCurricularActivityController::class, 'bulkStore']);
    Route::post('tenants/{domain}/extra-curricular', [ExtraCurricularActivityController::class, 'store']);
    Route::put('tenants/{domain}/extra-curricular/{curricularId}', [ExtraCurricularActivityController::class, 'update']);
    Route::delete('tenants/{domain}/extra-curricular/{curricularId}', [ExtraCurricularActivityController::class, 'delete']);



    // ? For Uploading Gallery Images
    Route::post('tenants/{domain}/gallery', [GalleryController::class, 'store']);
    Route::post('tenants/{domain}/gallery/{galleryId}', [GalleryController::class, 'update']);
    Route::delete('tenants/{domain}/gallery/{galleryId}', [GalleryController::class, 'destroy']);





    //? To create the Classes
    Route::apiResource('tenants/{domain}/classes', ClassController::class)->except(['show', 'index']);


    //? To create Teachers
    Route::apiResource('tenants/{domain}/teachers', TeacherController::class)->except('index', 'show');
    Route::apiResource('tenants/{domain}/teacher-roles', TeacherRoleController::class)->except(['index', 'show']);

    //? TO create the Students
    Route::apiResource('tenants/{domain}/students', StudentController::class)->except('show', 'store');
    Route::post('tenants/{domain}/students/bulk-upload', [StudentController::class, 'bulkUpload']);

    // ? To Filter the students based on calss and their name
    Route::get('tenants/{domain}/students/class/{classId}', [StudentController::class, 'filterByClass']);
    Route::get('tenants/{domain}/students/class/{classId}/search', [StudentController::class, 'filterByClassAndName']);


    //? To create te Result 
    Route::apiResource('tenants/{domain}/students/results', ResultController::class)->except('store', 'destroy');

    // ? Get the Individual Student Result
    Route::get('tenants/{domain}/students/{studentId}/results', [ResultController::class, 'getStudentResultsById']);

    //? To filter the results of students based in class and the studentId and classId
    Route::get('tenants/{domain}/students/results/class/{classId}', [ResultController::class, 'resultByClass']);

    // ? Final Result Generation (Admin Only)
    Route::post('tenants/{domain}/final-results/generate', [FinalResultController::class, 'generate']);

    // Get full result for a specific student (Admin/Teacher)
    // Route::get('tenants/{domain}/students/{studentId}/full-result', [ResultController::class, 'studentResult']);





    // To manage the Events

    Route::apiResource('tenants/{domain}/events-type', EventTypeController::class)->except('index', 'show');
    Route::apiResource('tenants/{domain}/events', EventController::class)->except('index', 'show');


    // To manage the Notices
    Route::apiResource('tenants/{domain}/notices', NoticeController::class)->except('index', 'show');

    // To get te class While Sending the message. 
    Route::get('tenants/{domain}/sms-class', [SMSController::class, 'getClass']);

    // ? Academic Year Management
    Route::get('tenants/{domain}/academic-years/current', [AcademicYearController::class, 'current']);
    Route::apiResource('tenants/{domain}/academic-years', AcademicYearController::class)->except(['index', 'show']);
    Route::post('tenants/{domain}/academic-years/{id}/set-current', [AcademicYearController::class, 'setCurrent']);

    // ? Student Promotion and Graduation
    Route::post('tenants/{domain}/students/promote', [StudentPromotionController::class, 'promoteClass']);
    Route::post('tenants/{domain}/students/graduate', [StudentPromotionController::class, 'markGraduated']);

    // ? Posts Management (Admin Only)
    Route::get('tenants/{domain}/admin/posts', [PostController::class, 'adminIndex']);
    Route::patch('tenants/{domain}/posts/{id}/status', [PostController::class, 'updateStatus']);

    // ? SMS sending (Admin Only)
    Route::post('tenants/{domain}/send-sms', [SMSController::class, 'send']);
    Route::get('tenants/{domain}/send-sms-teachers', [SMSController::class, 'sendToTeachers']);
    Route::get('tenants/{domain}/sms-usage', [SMSController::class, 'usage']);
    Route::get('tenants/{domain}/sms-messages', [SMSController::class, 'messages']);

    // SMS Settings (per academic year / events)
    Route::get('tenants/{domain}/sms-settings', [SMSSettingController::class, 'index']);
    Route::post('tenants/{domain}/sms-settings', [SMSSettingController::class, 'store']);
    Route::put('tenants/{domain}/sms-settings/{id}', [SMSSettingController::class, 'update']);
    Route::delete('tenants/{domain}/sms-settings/{id}', [SMSSettingController::class, 'destroy']);

    // Attendance Management
    Route::get('tenants/{domain}/attendances', [AttendanceController::class, 'index']);
    Route::post('tenants/{domain}/attendances', [AttendanceController::class, 'store']);
    Route::post('tenants/{domain}/attendances/bulk', [AttendanceController::class, 'bulkStore']);
    Route::get('tenants/{domain}/attendances/class/{classId}', [AttendanceController::class, 'classAttendance']);



    // Create Voices for admin
    Route::apiResource('tenants/{domain}/voices', VoiceController::class)->except('index', 'show');

    //
});

// ?For the Teachers and admin
Route::middleware(['tenant', 'auth:sanctum', 'role:admin,teacher'])->group(function () {


    Route::post('tenants/{domain}/teachers/me', [TeacherController::class, 'me']);
    Route::get('tenants/{domain}/teachers/{id}/history', [TeacherController::class, 'history']);
    Route::get('tenants/{domain}/result-settings', [ResultSettingController::class, 'index']);

    Route::get('tenants/{domain}/classes/{id}', [ClassController::class, 'show']);
    Route::get('tenants/{domain}/classes', [ClassController::class, 'index']);


    Route::post('tenants/{domain}/students', [StudentController::class, 'store']);
    Route::get('tenants/{domain}/students/class/{classId}', [StudentController::class, 'filterByClass']);
    Route::post('tenants/{domain}/students/results', [ResultController::class, 'store']);


    Route::post('tenants/{domain}/students/results/create', [ResultController::class, 'createResultByTeacher']);
    // To get the data of result
    Route::post('tenants/{domain}/results/class', [ResultController::class, 'createClassResultByTeacher']);
    Route::put('tenants/{domain}/results/class/edit', [ResultController::class, 'editClassResultByTeacher']);
    Route::get('tenants/{domain}/classes/{classId}/results', [ResultController::class, 'getWholeClassResults']);

    Route::get('tenants/{domain}/classes/{classId}/subjects', [SubjectController::class, 'getSubjectsByClass']);
    Route::post('tenants/{domain}/class-subjects', [SubjectController::class, 'storeClassSubjectTeacher']);
    Route::put('tenants/{domain}/class-subjects/{id}', [SubjectController::class, 'updateClassSubjectTeacher']);
    Route::get('tenants/{domain}/class-subjects-teacher', [SubjectController::class, 'getClassSubjectTeacher']);

    //? To view the result for the teachers 
    Route::get('tenants/{domain}/teachers/{teacherId}/results', [ResultController::class, 'resultsByTeacher']);

    Route::get('tenants/{domain}/results/{classId}', [ResultController::class, 'classLedger']);

    // ? Bulk Upload
    Route::post('tenants/{domain}/students/bulk-upload', [StudentController::class, 'bulkUpload']);
    Route::post('tenants/{domain}/students/results/bulk-upload', [ResultController::class, 'bulkStore']);

    // ? To generate final result
    Route::post('tenants/{domain}/classes/{classId}/generate-final', [ResultController::class, 'generateClassFinalResult']);

    // ? To get final results
    Route::get('tenants/{domain}/classes/{classId}/final-results', [ResultController::class, 'getClassFinalResults']);
    Route::get('tenants/{domain}/students/{studentId}/final-result', [ResultController::class, 'getStudentFinalResult']);

    // ? Academic Year Viewing
    Route::get('tenants/{domain}/academic-years', [AcademicYearController::class, 'index']);
    Route::get('tenants/{domain}/academic-years/{id}', [AcademicYearController::class, 'show']);

    // ? Class History viewing
    Route::get('tenants/{domain}/classes/{classId}/history', [StudentPromotionController::class, 'getClassHistory']);
});

// ?For the students and parents
Route::middleware(['tenant', 'auth:sanctum', 'role:student,parent,admin,teacher'])->group(function () {
    Route::post('tenants/{domain}/students/profile', [StudentController::class, 'profile']);
    Route::get('tenants/{domain}/students/{id}', [StudentController::class, 'show']);

    Route::get('tenants/{domain}/students/result', [ResultController::class, 'studentResult']);
    // To get the events
    Route::get('tenants/{domain}/events', [EventController::class, 'index']);
    Route::get('tenants/{domain}/admin/events/{id}', [EventController::class, 'show']);

    // Delete Result (Admin/Teacher)
    Route::delete('tenants/{domain}/students/results/{id}', [ResultController::class, 'destroy']);

    // ? Student History viewing
    Route::get('tenants/{domain}/students/{id}/history', [StudentPromotionController::class, 'getStudentHistory']);

    // ? Posts Creation and Management (Students, Teachers, Admin)
    Route::post('tenants/{domain}/posts', [PostController::class, 'store']);
    Route::put('tenants/{domain}/posts/{id}', [PostController::class, 'update']);
    Route::delete('tenants/{domain}/posts/{id}', [PostController::class, 'destroy']);

    // My Attendance
    Route::get('tenants/{domain}/my-attendance', [AttendanceController::class, 'myAttendance']);

    // ? Final Result Viewing (GradeSheet)
    Route::get('tenants/{domain}/final-results/class/{classId}', [FinalResultController::class, 'getResults']);
});


// No need to login Routes
Route::middleware(['tenant'])->group(function () {

    Route::get("/tenants/{domain}/contacts", action: [ContactController::class, 'store']);

    // ? Quick Links 
    Route::get("/tenants/{domain}/quick-links", [QuickLinkContrtoller::class, 'index']);
    Route::get("/tenants/{domain}/quick-links/{id}", [QuickLinkContrtoller::class, 'show']);


    Route::get('tenants/{domain}/voices', [VoiceController::class, 'index']);
    Route::get('tenants/{domain}/voices/{id}', [VoiceController::class, 'show']);


    Route::get('tenants/{domain}/events-type', [EventTypeController::class, 'index']);
    Route::get('tenants/{domain}/events-type/{id}', [EventTypeController::class, 'show']);

    //? To show the counts in admin panel
    Route::get('tenants/{domain}/admin/dashboard/stats', [DashboardController::class, 'stats']);

    //? For Club Section
    Route::get('tenants/{domain}/clubs', [ClubCOntroller::class, 'index']);
    Route::get('tenants/{domain}/clubs/all-students', [ClubCOntroller::class, 'allClubStudents']);
    Route::get('tenants/{domain}/clubs/{id}', [ClubCOntroller::class, 'show']);

    Route::get('tenants/{domain}/clubs/{id}/students', [ClubController::class, 'students']);


    // ? Analytical Report
    Route::get('tenants/{domain}/analytical-report', [AnalyticalReportController::class, 'index']);
    // ?To get the Details of the school (Settings)
    Route::get('/tenants/{domain}/settings', [SettingController::class, 'index']);
    Route::get('tenants/{domain}/website-settings', [WebsiteSettingController::class, 'index']);

    //?  To get the School Members 
    Route::get('tenants/{domain}/school-members', [App\Http\Controllers\Admin\SchoolMemberController::class, 'index']);
    Route::get('tenants/{domain}/school-members/{id}', [App\Http\Controllers\Admin\SchoolMemberController::class, 'show']);

    // To get the events
    Route::get('tenants/{domain}/events', [EventController::class, 'index']);
    Route::get('tenants/{domain}/events/{id}', [EventController::class, 'show']);
    // To get the notices
    Route::get('tenants/{domain}/notices', [NoticeController::class, 'index']);
    Route::get('tenants/{domain}/notices/{id}', [NoticeController::class, 'show']);
    Route::get('tenants/{domain}/teachers', action: [TeacherController::class, 'index']);
    Route::get('tenants/{domain}/teachers/{id}', action: [TeacherController::class, 'show']);
    //? Route::get('tenants/{domain}/teachers/{id}', action: [TeacherController::class, 'show']);
    Route::get('tenants/{domain}/teacher-roles', [TeacherRoleController::class, 'index']);


    //? Report api for school
    Route::get('tenants/{domain}/reports', [ReportController::class, 'getReports']);


    //? Gallery routes for website
    Route::get('tenants/{domain}/gallery', [GalleryController::class, 'index']);
    Route::get('tenants/{domain}/gallery/{id}', [GalleryController::class, 'show']);

    // ? Posts Public routes
    Route::get('tenants/{domain}/posts', [PostController::class, 'index']);
    Route::get('tenants/{domain}/posts/{id}', [PostController::class, 'show']);
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

    // Aggregated Teachers and Students by Local Unit
    Route::get('/gov/all-data/{localUnit}', [\App\Http\Controllers\Government\LocalUnitDataController::class, 'getAllTeachersAndStudents']);
    Route::get('/gov/all-students/{localUnit}', [\App\Http\Controllers\Government\LocalUnitDataController::class, 'getAllStudents']);


    Route::post('/gov/analytics/ethnicity', [AnalyticsController::class, 'ethnicity']);
    Route::post('/gov/analytics/comprehensive', [AnalyticsController::class, 'comprehensive']);
    Route::post('/gov/analytics/class-activity', [AnalyticsController::class, 'getClassActivityReport']);
    Route::post('/gov/analytics/teacher-activity', [AnalyticsController::class, 'getTeacherAnalyticsReport']);

});
