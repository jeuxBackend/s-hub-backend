<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;

use App\Http\Controllers\Auth\{
    SignupController,
    LoginController,
    VerifyOtpController,
    ResendOtpController,
    LogoutController
};
use App\Http\Controllers\Api\{
    User\UserController,
    Subject\SubjectController,
    Institute\InstituteController,
    Student\StudentController,
    Grade\GradeController,
    Setting\SettingController
};
use App\Http\Controllers\Api\Principal\{
    PrincipalDashboardController,
    TeacherController,
    GuardianController,
    SchoolAdminController
};
use App\Http\Controllers\Api\Classroom\ClassroomController;
use App\Http\Controllers\Api\ClassroomTeacher\ClassroomTeacherController;
use App\Http\Controllers\Api\StudentFee\AssignFeeController;
use App\Http\Controllers\Api\Attendance\AttendanceController;

// 📦 Rate Limiting
RateLimiter::for('otp-resend', fn ($request) => Limit::perHour(5)->by($request->ip()));

// 🔓 Public Routes
Route::get('/ping', fn () => 'pong');
Route::post('/signup', SignupController::class);
Route::post('/login', LoginController::class);
Route::post('/otp/verify', VerifyOtpController::class);
Route::post('/otp/resend', ResendOtpController::class)->middleware('throttle:otp-resend');

// 🔐 Authenticated & Active Users
Route::middleware(['auth:sanctum', 'active.user'])->group(function () {
    Route::post('/logout', LogoutController::class);

    // 🌟 Principal, School Admin, Teacher (OTP Verified)
    Route::middleware(['otp.verified', 'role:principal,school_admin,teacher'])->prefix('v1')->group(function () {
        // 👤 Users
        Route::get('users', [UserController::class, 'index']);
        Route::get('users/{user}', [UserController::class, 'show']);
        Route::put('users/{user}', [UserController::class, 'update']);
        Route::delete('users/{user}', [UserController::class, 'destroy']);
        Route::patch('users/{user}/notifications/toggle', [UserController::class, 'toggleNotification']);
        Route::put('update-contact', [UserController::class, 'updateContact']);
        Route::put('change-password', [UserController::class, 'changePassword']);

        // 📚 Students
        Route::apiResource('students', StudentController::class);
        Route::post('signup/others', SignupController::class);

        // 🧑‍🏫 Teachers
        Route::get('teachers', TeacherController::class);
        Route::put('teachers/{user}', [UserController::class, 'update']);
        Route::delete('teachers/{user}', [UserController::class, 'destroy']);

        // 🧑‍🎓 Guardians
        Route::get('guardians', GuardianController::class);
        Route::put('guardians/{user}', [UserController::class, 'update']);
        Route::delete('guardians/{user}', [UserController::class, 'destroy']);

        // 🏫 School Admins
        Route::get('school-admins', SchoolAdminController::class);
        Route::put('school-admins/{user}', [UserController::class, 'update']);
        Route::delete('school-admins/{user}', [UserController::class, 'destroy']);

        // 🧑‍🏫 Classroom Teacher Allocation
        Route::prefix('classroom-teachers')->group(function () {
            Route::post('allocate', [ClassroomTeacherController::class, 'allocate']);
            Route::post('unallocate', [ClassroomTeacherController::class, 'unallocate']);
        });

        // 🏫 Classrooms (Full Access)
        Route::apiResource('classrooms', ClassroomController::class);

        // 📘 Subjects (Full Access)
        Route::apiResource('subjects', SubjectController::class);

        // 📅 Attendance
        Route::prefix('attendances')->group(function () {
            Route::patch('mark', [AttendanceController::class, 'mark']);
            Route::get('by-date', [AttendanceController::class, 'getByDate']);
            Route::get('by-month', [AttendanceController::class, 'getByMonth']);
        });

        // 🎓 Grades (per classroom)
        Route::prefix('classrooms/{classroom}/grades')->group(function () {
            Route::get('/', [GradeController::class, 'index']);
            Route::post('/', [GradeController::class, 'store']);
             Route::patch('{grade}', [GradeController::class, 'update']); 
        });

        // 📊 Stats & Settings
        Route::get('dashboard-stats', [PrincipalDashboardController::class, 'stats']);
        Route::get('settings', [SettingController::class, 'show']);

        // 💵 Student Fees
        Route::post('student-fees/assign', AssignFeeController::class);
    });

    // 🌟 View-Only Access for All Roles
    Route::prefix('v1')->group(function () {
        Route::get('institute/{institute}', [InstituteController::class, 'update']); // if only update access
        Route::get('classrooms', [ClassroomController::class, 'index']);
        Route::get('classrooms/{classroom}', [ClassroomController::class, 'show']);
        Route::get('subjects', [SubjectController::class, 'index']);
        Route::get('subjects/{subject}', [SubjectController::class, 'show']);
        Route::put('users/{user}', [UserController::class, 'update']);
        Route::get('by-month', [AttendanceController::class, 'getByMonth']);
    });

    // 🌟 Reserved Zone: Admin/Sub Admin Only
    Route::middleware('role:admin,sub_admin')->prefix('v1')->group(function () {
        // reserved for future admin routes
    });
});

// 🔚 Fallback
Route::fallback(fn () => response()->json(['message' => 'Not Found'], 404));
