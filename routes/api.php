<?php

use App\Http\Controllers\Api\Admin\ClassnameController;
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
use App\Http\Controllers\Api\Alerts\SchoolAlertController;
use App\Http\Controllers\Auth\AdminLoginController;
use Illuminate\Support\Facades\Broadcast;
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
    Principal\StudentPromotionController as PrincipalStudentPromotionController,
    Principal\TeacherController,
    Principal\GuardianController,
    Principal\FreePeriodTeacherController,
    Setting\SettingController,
    Student\StudentController,
    StudentFee\AssignFeeController,
    Subject\SubjectController,
    User\UserController
};
use App\Http\Controllers\Api\Teacher\TeacherClassroomController;
use App\Http\Controllers\Api\Teacher\TeacherAttendanceController;
use App\Http\Controllers\Api\Chat\ChatUserController;
use App\Http\Controllers\Api\Chat\ConversationController as ChatConversationController;
use App\Http\Controllers\Api\Chat\MessageController as ChatMessageController;
use App\Http\Controllers\Api\Principal\TeacherAttendanceController as PrincipalTeacherAttendanceController;
use App\Http\Controllers\Api\Parent\StudentPromotionController as ParentStudentPromotionController;


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
Route::post('/stripe/webhook', [\App\Http\Controllers\Api\StripeWebhookController::class, 'handleWebhook']);
// });
Route::post('/logout', LogoutController::class)->middleware('auth:sanctum');
// ==================== AUTHENTICATED ROUTES ====================
Route::prefix('v1')->middleware(['auth:sanctum', 'active.user'])->group(function () {
    // WebSocket Broadcasting Authentication (Sanctum protected)
    Broadcast::routes();

    // ===================== OPERATIONAL ROUTES (TEACHER, PRINCIPAL, SCHOOL ADMIN) =====================
    Route::apiResource('student-reports', \App\Http\Controllers\Api\StudentReportController::class)->only(['index', 'store', 'destroy']);

    Route::patch('users/allow-alert/toggle', [UserController::class, 'toggleAllowAlert']);

    // Class names: per-institution, managed by admin/sub_admin/manager only.
    // Listing/viewing is open to any authenticated, active user regardless of role.
    Route::get('classnames', [ClassnameController::class, 'index']);
    Route::get('classnames/{classname}', [ClassnameController::class, 'show']);

    Route::middleware('role:admin,sub_admin,manager')->group(function () {
        Route::post('classnames', [ClassnameController::class, 'store']);
        Route::put('classnames/{classname}', [ClassnameController::class, 'update']);
        Route::patch('classnames/{classname}', [ClassnameController::class, 'update']);
        Route::delete('classnames/{classname}', [ClassnameController::class, 'destroy']);
    });

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
    Route::middleware(['otp.verified', 'role:teacher,school-admin'])->group(function () {
        Route::get('teacher/classrooms', [TeacherClassroomController::class, 'index']);
        Route::get('teacher/classrooms/{classroom}', [TeacherClassroomController::class, 'show']);
        Route::get('teacher/timetable', [TeacherClassroomController::class, 'timetable']);
        Route::get('teacher/academic-documents', [\App\Http\Controllers\Api\Teacher\AcademicDocumentController::class, 'index']);
        Route::get('teacher/subject-documents/allowed-combinations', [\App\Http\Controllers\Api\Teacher\SubjectDocumentController::class, 'allowedAssignments']);
        Route::get('teacher/subject-documents', [\App\Http\Controllers\Api\Teacher\SubjectDocumentController::class, 'index']);
        Route::post('teacher/subject-documents', [\App\Http\Controllers\Api\Teacher\SubjectDocumentController::class, 'store']);
        Route::patch('teacher/subject-documents/{subjectDocument}', [\App\Http\Controllers\Api\Teacher\SubjectDocumentController::class, 'update']);
        Route::delete('teacher/subject-documents/{subjectDocument}', [\App\Http\Controllers\Api\Teacher\SubjectDocumentController::class, 'destroy']);
        Route::get('teacher/subject-documents/{subjectDocument}/download', [\App\Http\Controllers\Api\Teacher\SubjectDocumentController::class, 'download'])->name('teacher.subject-documents.download');
        Route::put('teacher/profile', [UserController::class, 'updateProfile']);
        Route::post('teacher/profile', [UserController::class, 'updateProfile']);
        Route::post('teacher/attendance/mark', [TeacherAttendanceController::class, 'markAttendance']);
        Route::post('teacher/attendance/sign-out', [TeacherAttendanceController::class, 'signOut']);
        Route::get('teacher/attendance/history', [TeacherAttendanceController::class, 'history']);
        Route::post('teacher/proxy-attendance/mark', [FreePeriodTeacherController::class, 'markProxyAttendance']);

        // Assignment Routes - Teacher Side
        Route::apiResource('assignments', \App\Http\Controllers\Api\Assignment\AssignmentController::class);
    });

    // Assignment Routes - Parent Side
    Route::middleware(['otp.verified', 'role:parent'])->prefix('parent')->group(function () {
        Route::get('assignments', [\App\Http\Controllers\Api\Assignment\ParentAssignmentController::class, 'index']);
        Route::get('assignments/{assignment}', [\App\Http\Controllers\Api\Assignment\ParentAssignmentController::class, 'show']);
        Route::get('students/{student}/assignments', [\App\Http\Controllers\Api\Assignment\ParentAssignmentController::class, 'assignmentsForChild']);
        Route::get('academic-documents', [\App\Http\Controllers\Api\Parent\AcademicDocumentController::class, 'index']);
        Route::get('subject-documents', [\App\Http\Controllers\Api\Parent\SubjectDocumentController::class, 'index']);
        Route::get('subject-documents/{subjectDocument}/download', [\App\Http\Controllers\Api\Parent\SubjectDocumentController::class, 'download'])->name('parent.subject-documents.download');
        Route::get('family-members', [\App\Http\Controllers\Api\Parent\FamilyMemberController::class, 'index']);
        Route::post('family-members', [\App\Http\Controllers\Api\Parent\FamilyMemberController::class, 'store']);
        Route::post('family-members/{familyMember}', [\App\Http\Controllers\Api\Parent\FamilyMemberController::class, 'update']);
        Route::delete('family-members/{familyMember}', [\App\Http\Controllers\Api\Parent\FamilyMemberController::class, 'destroy']);
        Route::get('authorized-pickup', [\App\Http\Controllers\Api\Parent\AuthorizedPickupController::class, 'index']);
        Route::post('authorized-pickup', [\App\Http\Controllers\Api\Parent\AuthorizedPickupController::class, 'store']);
        Route::patch('authorized-pickup/{authorizedPickup}/set-current', [\App\Http\Controllers\Api\Parent\AuthorizedPickupController::class, 'setCurrent']);
        Route::patch('authorized-pickup/{authorizedPickup}', [\App\Http\Controllers\Api\Parent\AuthorizedPickupController::class, 'update']);
        Route::delete('authorized-pickup/{authorizedPickup}', [\App\Http\Controllers\Api\Parent\AuthorizedPickupController::class, 'destroy']);
    });

    // Authorized pickups - list all: parent viewing their own, or school staff viewing a parent in their institution.
    Route::middleware(['otp.verified', 'role:parent,teacher,school-admin,principal'])->group(function () {
        Route::get('parent/authorized-pickup/all', [\App\Http\Controllers\Api\Parent\AuthorizedPickupController::class, 'all']);
    });

    Route::put('update-profile', [UserController::class, 'updateProfile']);
    // Route::patch('users/{user}/notifications/toggle', [UserController::class, 'toggleNotification']);
    Route::patch('users/remote/toggle', [UserController::class, 'toggleRemote']);
    Route::get('me', [UserController::class, 'getAuthenticatedUser']);

    Route::middleware(['otp.verified'])->prefix('alerts')->group(function () {
        Route::get('current', [SchoolAlertController::class, 'current']);
        Route::get('responses', [SchoolAlertController::class, 'responses']);
        Route::post('abduction/trigger', [SchoolAlertController::class, 'triggerAbduction']);
        Route::post('emergency/trigger', [SchoolAlertController::class, 'triggerEmergency']);
        Route::post('{alert}/confirm', [SchoolAlertController::class, 'confirmAlert']);
        Route::post('{alert}/resolve', [SchoolAlertController::class, 'resolve']);
        Route::post('{alert}/respond', [SchoolAlertController::class, 'respond']);
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
            Route::patch('institution/slogan', [InstituteController::class, 'updateSlogan']);
            Route::post('institution/slogan', [InstituteController::class, 'updateSlogan']);
            Route::patch('student-reports/{id}/status', [\App\Http\Controllers\Api\StudentReportController::class, 'updateStatus']);
            Route::get('timetable/config', [\App\Http\Controllers\Api\Principal\TimetableConfigController::class, 'showCurrent']);
            Route::put('timetable/config', [\App\Http\Controllers\Api\Principal\TimetableConfigController::class, 'upsertConfig']);
            Route::get('timetable/teacher-availabilities', [\App\Http\Controllers\Api\Principal\TimetableConfigController::class, 'indexTeacherAvailabilities']);
            Route::get('timetable/class-subject-requirements', [\App\Http\Controllers\Api\Principal\TimetableConfigController::class, 'indexClassSubjectRequirements']);
            Route::post('timetable/teacher-availabilities', [\App\Http\Controllers\Api\Principal\TimetableConfigController::class, 'bulkUpsertTeacherAvailabilities']);
            Route::patch('timetable/teacher-availabilities/{id}', [\App\Http\Controllers\Api\Principal\TimetableConfigController::class, 'updateTeacherAvailability']);
            Route::delete('timetable/teacher-availabilities/{id}', [\App\Http\Controllers\Api\Principal\TimetableConfigController::class, 'destroyTeacherAvailability']);
            Route::post('timetable/class-subject-requirements', [\App\Http\Controllers\Api\Principal\TimetableConfigController::class, 'bulkUpsertClassSubjectRequirements']);
            Route::patch('timetable/class-subject-requirements/{id}', [\App\Http\Controllers\Api\Principal\TimetableConfigController::class, 'updateClassSubjectRequirement']);
            Route::delete('timetable/class-subject-requirements/{id}', [\App\Http\Controllers\Api\Principal\TimetableConfigController::class, 'destroyClassSubjectRequirement']);
            Route::post('timetable/generate-preview', [\App\Http\Controllers\Api\Principal\TimetableGenerationController::class, 'preview']);
            Route::post('timetable/apply', [\App\Http\Controllers\Api\Principal\TimetableGenerationController::class, 'apply']);
            Route::post('timetable/entries', [\App\Http\Controllers\Api\Principal\TimetableGenerationController::class, 'storeEntry']);
            Route::patch('timetable/entries/lock', [\App\Http\Controllers\Api\Principal\TimetableGenerationController::class, 'bulkLockEntries']);
            Route::patch('timetable/entries/bulk-move', [\App\Http\Controllers\Api\Principal\TimetableGenerationController::class, 'bulkMoveEntries']);
            Route::post('timetable/entries/swap', [\App\Http\Controllers\Api\Principal\TimetableGenerationController::class, 'swapEntries']);
            Route::patch('timetable/entries/{id}', [\App\Http\Controllers\Api\Principal\TimetableGenerationController::class, 'updateEntry']);
            Route::delete('timetable/entries/{id}', [\App\Http\Controllers\Api\Principal\TimetableGenerationController::class, 'destroyEntry']);
            Route::get('school-timetable', [\App\Http\Controllers\Api\Principal\PrincipalTimetableController::class, 'getSchoolTimetable']);
            Route::get('teachers/{id}/timetable', [\App\Http\Controllers\Api\Principal\PrincipalTimetableController::class, 'getTeacherTimetable']);
            Route::get('classrooms/{id}/timetable', [\App\Http\Controllers\Api\Principal\PrincipalTimetableController::class, 'getClassroomTimetable']);
            Route::get('subjects/{id}/timetable', [\App\Http\Controllers\Api\Principal\PrincipalTimetableController::class, 'getSubjectTimetable']);
            Route::post('final-results-submissions', [\App\Http\Controllers\Api\Principal\FinalResultSubmissionController::class, 'store']);

            // Add new endpoints for classroom statistics
            Route::get('classrooms/{id}/average-attendance', [ClassroomController::class, 'getAverageAttendance']);
            Route::get('classrooms/{id}/average-performance', [ClassroomController::class, 'getAveragePerformance']);
            Route::get('classrooms/{id}/tuition-paid-owed', [ClassroomController::class, 'getTuitionPaidOwed']);
        });



        Route::prefix('principal')->middleware('role:principal,school-admin')->group(function () {
            // Specific student routes MUST come before apiResource to avoid {id} conflicts
            Route::get('students/year-marks', [StudentController::class, 'allYearMarks'])->middleware('schooladmin.permission:Students');
            Route::get('students/{id}/with-invoices', [StudentController::class, 'showWithInvoices'])->middleware('schooladmin.permission:Students');
            Route::get('students/{id}/year-marks', [StudentController::class, 'yearMarks'])->middleware('schooladmin.permission:Students');
            Route::apiResource('students', StudentController::class)->middleware('schooladmin.permission:Students');
            Route::get('teachers/teaching-assignments', [TeacherController::class, 'teachingAssignments'])->middleware('schooladmin.permission:Teachers');
            Route::apiResource('teachers', TeacherController::class)->middleware('schooladmin.permission:Teachers');
            Route::apiResource('parents', GuardianController::class)->middleware('schooladmin.permission:Parents');
            Route::get('classrooms/no-fee-students', [ClassroomController::class, 'indexWithoutFeeStudents']);
            Route::post('classrooms/in-charge', [ClassroomController::class, 'assignInCharge']);
            Route::get('classrooms/{id}/subject-performance', [ClassroomController::class, 'subjectPerformance']);
            Route::get('classrooms/{id}/performance-stats', [ClassroomController::class, 'performanceStats']);
            Route::apiResource('classrooms', ClassroomController::class);
            Route::apiResource('subjects', SubjectController::class);
            Route::get('student-promotions', [PrincipalStudentPromotionController::class, 'index']);
            Route::post('students/{student}/promote', [PrincipalStudentPromotionController::class, 'store']);
            Route::post('classrooms/{classroom}/exam-schedules', [\App\Http\Controllers\Api\Principal\AcademicDocumentController::class, 'storeExamSchedule']);
            Route::post('classrooms/{classroom}/test-schedules', [\App\Http\Controllers\Api\Principal\AcademicDocumentController::class, 'storeTestSchedule']);
            Route::post('students/{student}/transcripts', [\App\Http\Controllers\Api\Principal\AcademicDocumentController::class, 'storeTranscript']);
            Route::patch('academic-documents/{academicDocument}', [\App\Http\Controllers\Api\Principal\AcademicDocumentController::class, 'update']);
            Route::get('subject-documents', [\App\Http\Controllers\Api\Principal\SubjectDocumentController::class, 'index']);
            Route::get('subject-documents/{subjectDocument}/download', [\App\Http\Controllers\Api\Principal\SubjectDocumentController::class, 'download'])->name('principal.subject-documents.download');
            Route::get('classrooms/{classroom}/academic-documents', [\App\Http\Controllers\Api\Principal\AcademicDocumentController::class, 'getClassroomAcademicDocuments']);
            Route::get('classrooms/{classroom}/transcripts', [\App\Http\Controllers\Api\Principal\AcademicDocumentController::class, 'getClassroomTranscripts']);
            Route::post('rota/preview', [\App\Http\Controllers\Api\Principal\RotaController::class, 'preview']);
            Route::post('rota/apply', [\App\Http\Controllers\Api\Principal\RotaController::class, 'apply']);

            Route::post('send-noticeboard', [NotificationsController::class, 'sendNoticeboard']);

            Route::get('classrooms-list', [ClassroomController::class, 'getClassRoomsList']);
            Route::get('classrooms-with-subjects', [ClassroomController::class, 'getClassroomsWithSubjectsAndTeachers']);

            Route::post('classroom-teachers/allocate', [ClassroomTeacherController::class, 'allocate']);
            Route::post('classroom-teachers/unallocate', [ClassroomTeacherController::class, 'unallocate']);

            // Free Period Teacher - Notify and Mark Extra Attendance
            Route::post('free-period-teachers/notify', [FreePeriodTeacherController::class, 'notifyFreeTeachers']);
            Route::post('free-period-teachers/notify-teacher', [FreePeriodTeacherController::class, 'notifyTeacher']);
            Route::get('teacher-attendance/{teacherId}', [PrincipalTeacherAttendanceController::class, 'index']);
            Route::post('teachers/free-during-time', [PrincipalTeacherAttendanceController::class, 'getFreeTeachers']);

            // Fee Payment Notifications
            Route::post('fee-notifications/notify', [\App\Http\Controllers\Api\Principal\FeeNotificationController::class, 'notify']);
            Route::get('fee-notifications/schedule', [\App\Http\Controllers\Api\Principal\FeeNotificationController::class, 'getSchedule']);
        });

        Route::put('update-contact', [UserController::class, 'updateContact']);
        // Route::put('change-password', [UserController::class, 'changePassword']);
        Route::patch('users/{user}/notifications/toggle', [UserController::class, 'toggleNotification']);


        // Finance
        Route::middleware('schooladmin.permission:Finance')->group(function () {
            Route::apiResource('student-invoices', \App\Http\Controllers\Api\StudentInvoiceController::class);
            Route::post('student-invoices/{student_invoice}/pay', [\App\Http\Controllers\Api\StudentInvoiceController::class, 'pay']);
            Route::get('student-invoices/{student_invoice}/receipt/download', [\App\Http\Controllers\Api\StudentInvoiceController::class, 'downloadReceipt']);
            Route::post('student-fees', [\App\Http\Controllers\Api\StudentFee\StudentFeeController::class, 'index']);
            Route::post('student-fees/assign', AssignFeeController::class);
            Route::patch('student-fees/{student_fee}', [\App\Http\Controllers\Api\StudentFee\StudentFeeController::class, 'update']);
            Route::post('tuition-updates/schedule', [\App\Http\Controllers\Api\TuitionUpdateController::class, 'schedule']);
        });

        Route::get('dashboard-stats', [PrincipalDashboardController::class, 'stats']);
        Route::get('principal/dashboard/academic-analytics', [PrincipalDashboardController::class, 'academicAnalytics']);
        // Route::get('settings', [SettingController::class, 'show']);
    });
    Route::post('get-noticeboard', [NotificationsController::class, 'getUserNotifications']);
    Route::post('read-noticeboard/{id}', [NotificationsController::class, 'readNotification']);
    Route::post('notifications/mark-all-read', [NotificationsController::class, 'markAllAsRead']);
    Route::get('notifications/unread-count', [NotificationsController::class, 'getUnreadCount']);

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
        Route::apiResource('schools', SchoolController::class)->only(['index', 'show', 'store', 'update', 'destroy'])->middleware('subadmin.permission:School');
        Route::apiResource('teachers', AdminTeacherController::class)->only(['index', 'show'])->middleware('subadmin.permission:Teachers');
        Route::apiResource('students', AdminStudentController::class)->only(['index', 'show'])->middleware('subadmin.permission:Students');
        Route::patch('schools/{id}/alert-feature', [SchoolController::class, 'toggleAlertFeature']);
        Route::get('categories', [\App\Http\Controllers\Api\Admin\CategoryController::class, 'index'])->middleware('subadmin.permission:School');
        Route::post('categories', [\App\Http\Controllers\Api\Admin\CategoryController::class, 'store'])->middleware('subadmin.permission:School');
        Route::delete('categories/{category}', [\App\Http\Controllers\Api\Admin\CategoryController::class, 'destroy'])->middleware('subadmin.permission:School');
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
        Route::get('categories', [\App\Http\Controllers\Api\Admin\CategoryController::class, 'index']);
        Route::post('categories', [\App\Http\Controllers\Api\Admin\CategoryController::class, 'store']);
        Route::delete('categories/{category}', [\App\Http\Controllers\Api\Admin\CategoryController::class, 'destroy']);

        // Stripe Connect for Managers
        Route::post('stripe/connect', [\App\Http\Controllers\Api\Manager\StripeConnectController::class, 'connect']);
        Route::get('stripe/status', [\App\Http\Controllers\Api\Manager\StripeConnectController::class, 'status']);
    });

    // ===================== PARENT ENDPOINTS =====================
    Route::middleware(['otp.verified', 'role:parent'])->prefix('parent')->group(function () {
        Route::get('students/year-marks', [StudentController::class, 'allYearMarks']);
        Route::get('students/{id}/year-marks', [StudentController::class, 'yearMarks']);
        Route::get('students', [\App\Http\Controllers\Api\Parent\ParentController::class, 'getChildrenClassrooms']);
        Route::get('attendances/by-month', [\App\Http\Controllers\Api\Parent\ParentController::class, 'getAttendanceByMonth']);
        Route::get('attendances/by-date', [\App\Http\Controllers\Api\Parent\ParentController::class, 'getAttendanceByDate']);
        Route::post('attendances/{attendance}/reason', [\App\Http\Controllers\Api\Parent\ParentController::class, 'updateAttendanceReason']);
        Route::get('grades', [\App\Http\Controllers\Api\Parent\ParentController::class, 'getGrades']);
        Route::get('alerts/current', [SchoolAlertController::class, 'current']);
        Route::get('alerts/responses', [SchoolAlertController::class, 'parentResponses']);
        Route::post('alerts/{alert}/respond', [SchoolAlertController::class, 'respond']);
        Route::get('results', [\App\Http\Controllers\Api\Parent\ParentResultController::class, 'index']);
        Route::get('results/{submission}/download/{student}', [\App\Http\Controllers\Api\Parent\ParentResultController::class, 'download']);
        Route::get('student-promotions', [ParentStudentPromotionController::class, 'index']);
        Route::post('student-promotions/{id}/approve', [ParentStudentPromotionController::class, 'approve']);
        Route::post('student-promotions/{id}/reject', [ParentStudentPromotionController::class, 'reject']);

        // Invoices & Payments
        Route::get('invoices', [\App\Http\Controllers\Api\Parent\ParentInvoiceController::class, 'index']);
        Route::get('invoices/{id}', [\App\Http\Controllers\Api\Parent\ParentInvoiceController::class, 'show']);
        Route::get('invoices/{id}/receipt/download', [\App\Http\Controllers\Api\Parent\ParentInvoiceController::class, 'downloadReceipt']);
        Route::post('invoices/{id}/pay', [\App\Http\Controllers\Api\Parent\ParentInvoiceController::class, 'pay']);
        Route::post('invoices/{id}/confirm', [\App\Http\Controllers\Api\Parent\ParentInvoiceController::class, 'confirm']);
        Route::post('child/picture/{id}', [\App\Http\Controllers\Api\Parent\ParentController::class, 'updateChildProfilePic']);
        // Route::get('settings', [SettingController::class, 'show']);
    });


    // ===================== CHAT ROUTES (ALL AUTHENTICATED USERS) =====================
    Route::middleware(['otp.verified'])->prefix('chat')->group(function () {
        Route::get('users', [ChatUserController::class, 'index']);                                        // Who can I chat with?
        Route::get('conversations', [ChatConversationController::class, 'index']);                        // My inbox
        Route::post('conversations', [ChatConversationController::class, 'store']);                       // Start / find conversation
        Route::get('conversations/{conversation}', [ChatConversationController::class, 'show']);          // Open chat (paginated messages)
        Route::post('conversations/{conversation}/messages', [ChatMessageController::class, 'store']);    // Send message
        Route::patch('conversations/{conversation}/read', [ChatMessageController::class, 'markRead']);    // Mark as read
    });

    // ===================== GENERAL REPORTS =====================
    Route::middleware(['otp.verified'])->group(function () {
        Route::patch('general-reports/{id}/status', [\App\Http\Controllers\Api\GeneralReportController::class, 'updateStatus']);
        Route::apiResource('general-reports', \App\Http\Controllers\Api\GeneralReportController::class)->only(['index', 'store', 'show', 'update', 'destroy']);
    });
    Route::get('settings', [SettingController::class, 'show']);
    Route::put('change-password', [UserController::class, 'changePassword']);
});



Route::fallback(fn() => response()->json(['message' => 'Not Found'], 404));
