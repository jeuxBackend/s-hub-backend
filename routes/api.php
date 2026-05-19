<?php

use App\Http\Controllers\Api\Admin\ActivityControler;
use App\Http\Controllers\Api\Admin\ManagerInvoiceController;
use App\Http\Controllers\Api\Admin\SchoolController;
use App\Http\Controllers\Api\Admin\ManagerController;
use App\Http\Controllers\Api\Admin\SubAdminController;
use App\Http\Controllers\Api\Admin\TeacherController as AdminTeacherController;
use App\Http\Controllers\Api\Manager\ActivitiesController;
use App\Http\Controllers\Api\Manager\TeacherController as ManagerTeacherController;
use App\Http\Controllers\Api\Admin\StudentController as AdminStudentController;
use App\Http\Controllers\Api\Manager\SchoolController as ManagerSchoolController;
use App\Http\Controllers\Api\Manager\PrincipalController as ManagerPrincipalController;
use App\Http\Controllers\Api\Manager\StudentController as ManagerStudentController;
use App\Http\Controllers\Api\Manager\ManagerDashboardController;
use App\Http\Controllers\Api\Notifications\NotificationsController;
use App\Http\Controllers\Auth\AdminLoginController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;

use App\Http\Controllers\Auth\{
    LoginController,
    LogoutController,
    ResendOtpController,
    SignupController,
    VerifyOtpController
};
use App\Http\Controllers\Api\{
    Attendance\AttendanceController,
    Classroom\ClassroomController,
    ClassroomTeacher\ClassroomTeacherController,
    Grade\GradeController,
    Institute\InstituteController,
    Principal\PrincipalDashboardController,
    Principal\SchoolAdminController,
    Principal\TeacherController,
    Principal\GuardianController,
    Setting\SettingController,
    Student\StudentController,
    StudentFee\AssignFeeController,
    Subject\SubjectController,
    User\UserController
};
use App\Http\Controllers\Api\Teacher\TeacherClassroomController;


// Rate Limiting
RateLimiter::for('otp-resend', fn($request) => Limit::perHour(5)->by($request->ip()));

// ==================== PUBLIC ROUTES ====================
Route::post('/login', LoginController::class);
// Route::prefix('v1')->group(function () {
Route::get('/ping', fn() => 'pong');

// Route::post('/signup', SignupController::class);
Route::post('/web/login', AdminLoginController::class);
Route::post('/otp/verify', VerifyOtpController::class);
Route::post('/otp/resend', ResendOtpController::class)->middleware('throttle:otp-resend');
// });
Route::post('/logout', LogoutController::class)->middleware('auth:sanctum');

// ==================== AUTHENTICATED ROUTES ====================
Route::prefix('v1')->middleware(['auth:sanctum', 'active.user'])->group(function () {
    // ===================== OPERATIONAL ROUTES (TEACHER, PRINCIPAL, SCHOOL ADMIN) =====================
    Route::apiResource('student-reports', \App\Http\Controllers\Api\StudentReportController::class)->only(['index', 'store', 'destroy']);

    Route::middleware(['otp.verified', 'role:principal,school-admin,teacher'])->group(function () {
        // Attendance
        Route::prefix('attendances')->group(function () {
            Route::patch('mark', [AttendanceController::class, 'mark']);
            Route::get('by-date', [AttendanceController::class, 'getByDate']);
            Route::get('by-month', [AttendanceController::class, 'getByMonth']);
        });

        // Student Performance & Grades
        Route::apiResource('student-performances', \App\Http\Controllers\Api\StudentPerformanceController::class);

        Route::prefix('classrooms/{classroom}/grades')->group(function () {
            Route::get('/', [GradeController::class, 'index']);
            Route::post('/', [GradeController::class, 'store']);
            Route::patch('{grade}', [GradeController::class, 'update']);
        });
    });

    // ===================== TEACHER ONLY ROUTES =====================
    Route::middleware(['otp.verified', 'role:teacher'])->group(function () {
        Route::get('teacher/classrooms', [TeacherClassroomController::class, 'index']);
        Route::get('teacher/classrooms/{classroom}', [TeacherClassroomController::class, 'show']);
    });


    // ===================== MANAGEMENT ROUTES (PRINCIPAL + SCHOOL ADMIN ONLY) =====================
    Route::middleware(['otp.verified', 'role:principal,school-admin'])->group(function () {
        Route::apiResource('users', UserController::class)->only(['index', 'show', 'update', 'destroy']);
        Route::prefix('principal')->middleware('role:principal')->group(function () {
            Route::apiResource('school-admins', SchoolAdminController::class);
            Route::post('change-role', [SchoolAdminController::class, 'makeSubAdmin']);
            Route::get('teachers-list', [SchoolAdminController::class, 'getTeachersListForSchoolAdmin']);
            Route::post('update-permissions', [SchoolAdminController::class, 'updatePermissions']);
            Route::get('remove-admin/{id}', [SchoolAdminController::class, 'removeSchoolAdmin']);
            Route::patch('student-reports/{id}/status', [\App\Http\Controllers\Api\StudentReportController::class, 'updateStatus']);
        });



        Route::prefix('principal')->middleware('role:principal,school-admin')->group(function () {
            // Managed by Principal or School Admin with permissions
            Route::apiResource('students', StudentController::class)->middleware('schooladmin.permission:Students');
            Route::apiResource('teachers', TeacherController::class)->middleware('schooladmin.permission:Teachers');
            Route::apiResource('parents', GuardianController::class)->middleware('schooladmin.permission:Parents');
            Route::apiResource('classrooms', ClassroomController::class);
            Route::apiResource('subjects', SubjectController::class);

            Route::post('send-noticeboard', [NotificationsController::class, 'sendNoticeboard']);

            Route::get('classrooms-list', [ClassroomController::class, 'getClassRoomsList']);

            Route::post('classroom-teachers/allocate', [ClassroomTeacherController::class, 'allocate']);
            Route::post('classroom-teachers/unallocate', [ClassroomTeacherController::class, 'unallocate']);
        });

        Route::put('update-contact', [UserController::class, 'updateContact']);
        Route::put('change-password', [UserController::class, 'changePassword']);
        Route::patch('users/{user}/notifications/toggle', [UserController::class, 'toggleNotification']);


        // Finance
        Route::middleware('schooladmin.permission:Finance')->group(function () {
            Route::apiResource('student-invoices', \App\Http\Controllers\Api\StudentInvoiceController::class);
            Route::post('student-fees', [\App\Http\Controllers\Api\StudentFee\StudentFeeController::class, 'index']);
            Route::post('student-fees/assign', AssignFeeController::class);
            Route::post('tuition-updates/schedule', [\App\Http\Controllers\Api\TuitionUpdateController::class, 'schedule']);
        });

        Route::get('dashboard-stats', [PrincipalDashboardController::class, 'stats']);
        Route::get('settings', [SettingController::class, 'show']);
    });
    Route::post('get-noticeboard', [NotificationsController::class, 'getUserNotifications']);
    Route::post('read-noticeboard/{id}', [NotificationsController::class, 'readNotification']);

    // ===================== VIEW-ONLY FOR ALL AUTH USERS =====================
    Route::get('school/{id}', [InstituteController::class, 'show']);
    Route::get('classrooms', [ClassroomController::class, 'index']);
    Route::get('classrooms/{classroom}', [ClassroomController::class, 'show']);
    Route::get('subjects', [SubjectController::class, 'index']);
    Route::get('subjects/{subject}', [SubjectController::class, 'show']);

    // ===================== GLOBAL ADMIN ONLY =====================
    Route::prefix('admin')->middleware('role:admin,sub_admin')->group(function () {
        Route::get('dashboard', [ActivityControler::class, 'dashboard']);

        Route::apiResource('managers', ManagerController::class);
        Route::get('managers/{id}/schools', [ManagerController::class, 'getManagerSchools']);

        Route::apiResource('sub-admins', SubAdminController::class);
        Route::apiResource('manager-invoices', ManagerInvoiceController::class);

        // Read-only views for Admin
        Route::apiResource('schools', SchoolController::class)->only(['index', 'show'])->middleware('subadmin.permission:School');
        Route::apiResource('teachers', AdminTeacherController::class)->only(['index', 'show'])->middleware('subadmin.permission:Teachers');
        Route::apiResource('students', AdminStudentController::class)->only(['index', 'show'])->middleware('subadmin.permission:Students');
    });

    // ===================== MANAGER ONLY =====================
    Route::prefix('manager')->middleware('role:manager')->group(function () {
        Route::apiResource('schools', ManagerSchoolController::class);
        Route::apiResource('principals', ManagerPrincipalController::class);
        Route::apiResource('teachers', ManagerTeacherController::class)->only(['index', 'show']);
        Route::apiResource('students', ManagerStudentController::class)->only(['index', 'show']);
        Route::apiResource('parents', \App\Http\Controllers\Api\Manager\GuardianController::class)->only(['index']);

        // Moderation
        Route::patch('students/{id}/toggle-block', [ManagerStudentController::class, 'toggleBlockStudent']);
        Route::patch('teachers/{id}/toggle-block', [ManagerTeacherController::class, 'toggleBlock']);
        Route::patch('parents/{id}/toggle-block', [\App\Http\Controllers\Api\Manager\GuardianController::class, 'toggleBlock']);

        Route::get('dashboard-stats', [ManagerDashboardController::class, 'stats']);
        Route::get('my-invoices', [ActivitiesController::class, 'getInvoices']);
    });
});



Route::fallback(fn() => response()->json(['message' => 'Not Found'], 404));
