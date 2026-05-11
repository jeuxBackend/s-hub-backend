<?php

use App\Http\Controllers\Api\Admin\ActivityControler;
use App\Http\Controllers\Api\Admin\ManagerInvoiceController;
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

// Rate Limiting
RateLimiter::for('otp-resend', fn($request) => Limit::perHour(5)->by($request->ip()));

// ==================== PUBLIC ROUTES ====================
Route::prefix('v1')->group(function () {
    Route::get('/ping', fn() => 'pong');

    Route::post('/signup', SignupController::class);
    Route::post('/login', LoginController::class);
    Route::post('/web/login', AdminLoginController::class);
    Route::post('/otp/verify', VerifyOtpController::class);
    Route::post('/otp/resend', ResendOtpController::class)
        ->middleware('throttle:otp-resend');
});

// ==================== AUTHENTICATED ROUTES ====================
Route::prefix('v1')->middleware(['auth:sanctum', 'active.user'])->group(function () {

    Route::post('/logout', LogoutController::class);

    // ===================== PRINCIPAL + SCHOOL ADMIN + TEACHER =====================
    Route::middleware(['otp.verified', 'role:principal,school_admin,teacher'])->group(function () {

        // Users
        Route::apiResource('users', UserController::class)->only(['index', 'show', 'update', 'destroy']);

        Route::put('update-contact', [UserController::class, 'updateContact']);
        Route::put('change-password', [UserController::class, 'changePassword']);
        Route::patch('users/{user}/notifications/toggle', [UserController::class, 'toggleNotification']);

        // Students, Classrooms, Subjects, etc.
        Route::apiResource('students', StudentController::class);
        Route::apiResource('classrooms', ClassroomController::class);
        Route::apiResource('subjects', SubjectController::class);

        // Teachers / Guardians / School Admins (better separation)
        Route::apiResource('teachers', TeacherController::class);
        Route::apiResource('guardians', GuardianController::class);
        Route::apiResource('school-admins', SchoolAdminController::class);

        // Other resources
        Route::post('classroom-teachers/allocate', [ClassroomTeacherController::class, 'allocate']);
        Route::post('classroom-teachers/unallocate', [ClassroomTeacherController::class, 'unallocate']);

        // Attendance
        Route::prefix('attendances')->group(function () {
            Route::patch('mark', [AttendanceController::class, 'mark']);
            Route::get('by-date', [AttendanceController::class, 'getByDate']);
            Route::get('by-month', [AttendanceController::class, 'getByMonth']);
        });

        // Grades
        Route::prefix('classrooms/{classroom}/grades')->group(function () {
            Route::get('/', [GradeController::class, 'index']);
            Route::post('/', [GradeController::class, 'store']);
            Route::patch('{grade}', [GradeController::class, 'update']);
        });

        Route::post('student-fees/assign', AssignFeeController::class);
        Route::get('dashboard-stats', [PrincipalDashboardController::class, 'stats']);
        Route::get('settings', [SettingController::class, 'show']);
    });

    // ===================== VIEW-ONLY FOR ALL AUTH USERS =====================
    Route::middleware('otp.verified')->group(function () {   // or without if not needed
        Route::get('institute/{institute}', [InstituteController::class, 'show']);
        Route::get('classrooms', [ClassroomController::class, 'index']);
        Route::get('classrooms/{classroom}', [ClassroomController::class, 'show']);
        Route::get('subjects', [SubjectController::class, 'index']);
        Route::get('subjects/{subject}', [SubjectController::class, 'show']);
    });

    // ===================== ADMIN ONLY =====================
    Route::middleware('role:admin,sub_admin')->group(function () {
        Route::get('dashboard', [ActivityControler::class, 'dashboard']);
        Route::get('managers', [ActivityControler::class, 'getManagers']);
        Route::get('managers/{id}/schools', [ActivityControler::class, 'getManagerSchools']);
        Route::apiResource('manager-invoices', ManagerInvoiceController::class);
        Route::apiResource('schools', SchoolController::class);
    });
});



Route::fallback(fn() => response()->json(['message' => 'Not Found'], 404));
