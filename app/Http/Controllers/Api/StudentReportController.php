<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StudentReport;
use App\Models\NotificationLog;
use App\Http\Resources\StudentReportResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Throwable;
use App\Enums\UserRole;
use App\Services\FirebaseNotificationService;
use App\Models\Student;
use App\Models\User;

class StudentReportController extends Controller
{
    // List reports depending on role
    public function index(Request $request)
    {
        try {
            $user = auth()->user();
            $query = StudentReport::with(['student', 'classroom', 'teacher'])
                ->where('institution_id', $user->institution_id)
                ->latest();

            if ($user->isRole(UserRole::Parent)) {
                // Parent sees only approved reports for their children
                $query->where('status', 'approved')
                    ->whereHas('student', function ($q) use ($user) {
                        $q->where('guardian_id', $user->id);
                    });
            } elseif ($user->isRole(UserRole::Teacher)) {
                // Teacher sees reports they created
                $query->where('teacher_id', $user->id);
            } elseif ($user->isRole(UserRole::Principal) || $user->isRole(UserRole::SchoolAdmin)) {
                // Principal/Admin sees all reports, can filter by status
                if ($request->has('status')) {
                    $query->where('status', $request->status);
                }
            } else {
                return $this->errorResponse('Unauthorized access', 403);
            }

            $reports = $query->paginate($request->get('per_page', 15));

            return $this->paginatedResponse(StudentReportResource::collection($reports), 'Reports retrieved successfully');
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    // Teacher creates a report
    public function store(Request $request, FirebaseNotificationService $firebaseNotificationService)
    {
        try {
            $user = auth()->user();

            if (!$user->isRole(UserRole::Teacher) && !$user->isRole(UserRole::SchoolAdmin)) {
                return $this->errorResponse('Only teachers can create student reports.', 403);
            }

            $validator = Validator::make($request->all(), [
                'student_id' => 'required|exists:students,id',
                'classroom_id' => 'nullable|exists:classrooms,id',
                'report_type' => 'required|string|max:100',
                'title' => 'nullable|string|max:255',
                'description' => 'nullable|string',
                'file' => 'required|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:5120', // 5MB max
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', 422, $validator->errors()->all());
            }

            $filePath = $request->file('file')->store('student_reports', 'public');

            $report = StudentReport::create([
                'institution_id' => $user->institution_id,
                'student_id' => $request->student_id,
                'classroom_id' => $request->classroom_id,
                'teacher_id' => $user->id,
                'report_type' => $request->report_type,
                'title' => $request->title,
                'description' => $request->description,
                'file_path' => $filePath,
                'status' => 'pending',
            ]);

            // Get the student to find their parent/guardian
            $student = Student::find($request->student_id);

            

            // Also send notification to the institution's principal
            if ($user->institution_id) {
                $principal = User::where('institution_id', $user->institution_id)
                    ->where('role', UserRole::Principal)
                    ->first();

                if ($principal) {
                    // Create notification log entry for principal
                    $principalNotification = NotificationLog::create([
                        'user_id' => $principal->id,
                        'type' => 'student_report_created',
                        'title' => 'New Student Report Created',
                        'message' => "A new report has been created for {$student->full_name} by {$user->full_name}",
                        'is_read' => false,
                        'meta' => [
                            'report_id' => $report->id,
                            'student_id' => $student->id,
                            'student_name' => $student->full_name,
                            'teacher_id' => $user->id,
                            'teacher_name' => $user->full_name,
                            'report_type' => $request->report_type,
                            'report_title' => $request->title,
                            'report_description' => $request->description,
                            'created_at' => now()->toISOString(),
                        ],
                        'sent_at' => now(),
                    ]);

                    // Send FCM notification to principal if they have FCM token and notifications enabled
                    if ($principal->notifications_enabled && $principal->fcm_token) {
                        $firebaseNotificationService->sendToToken(
                            $principal->fcm_token,
                            'New Student Report Created',
                            "A new report has been created for {$student->full_name} by {$user->full_name}",
                            [
                                'type' => 'student_report_created',
                                'report_id' => (string)$report->id,
                                'student_id' => (string)$student->id,
                                'student_name' => $student->full_name,
                                'teacher_name' => $user->full_name,
                            ]
                        );
                    }
                }
            }

            return $this->successResponse(new StudentReportResource($report), 'Report submitted for approval', 201);
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    // Principal/Admin approves or rejects a report
    public function updateStatus(Request $request, $id, FirebaseNotificationService $firebaseNotificationService)
    {
        try {
            $user = auth()->user();

            if (!$user->isRole(UserRole::Principal) && !$user->isRole(UserRole::SchoolAdmin)) {
                return $this->errorResponse('Unauthorized to review reports.', 403);
            }

            $validator = Validator::make($request->all(), [
                'status' => 'required|in:approved,rejected',
                'reason' => 'nullable|string|max:500', // Optional reason for rejection
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', 422, $validator->errors()->all());
            }

            $report = StudentReport::where('institution_id', $user->institution_id)->findOrFail($id);
            $report->status = $request->status;
            $report->save();

            // Handle notifications
            if ($report->status == 'rejected') {
                // Notify the teacher about the rejection
                $teacher = User::find($report->teacher_id);
                
                if ($teacher) {
                    NotificationLog::create([
                        'user_id' => $report->teacher_id,
                        'type' => 'report_rejected',
                        'title' => 'Student Report Rejected',
                        'message' => "The report you submitted for student ID {$report->student_id} was rejected." . ($request->reason ? " Reason: {$request->reason}" : " Please review and update."),
                        'is_read' => false,
                        'meta' => [
                            'report_id' => $report->id,
                            'student_id' => $report->student_id,
                            'student_name' => $report->student->full_name ?? 'Unknown',
                            'reviewer_id' => $user->id,
                            'reviewer_name' => $user->full_name,
                            'rejection_reason' => $request->reason,
                        ],
                        'sent_at' => now(),
                    ]);

                    // Send FCM notification to teacher if they have FCM token and notifications enabled
                    if ($teacher->notifications_enabled && $teacher->fcm_token) {
                        $firebaseNotificationService->sendToToken(
                            $teacher->fcm_token,
                            'Student Report Rejected',
                            "The report you submitted for {$report->student->full_name} was rejected." . ($request->reason ? " Reason: {$request->reason}" : ""),
                            [
                                'type' => 'report_rejected',
                                'report_id' => (string)$report->id,
                                'student_name' => $report->student->full_name ?? 'Unknown',
                                'rejection_reason' => $request->reason ?? '',
                            ]
                        );
                    }
                }
            } elseif ($report->status == 'approved') {
                // If you want to notify the parent when it's approved
                $student = $report->student;
                $student->full_name = $student->first_name . ' ' . $student->sur_name;
                if ($student && $student->guardian_id) {
                    $parent = User::find($student->guardian_id);
                    $parent->full_name = $parent->first_name . ' ' . $parent->sur_name;
                    if ($parent) {
                        NotificationLog::create([
                            'user_id' => $student->guardian_id,
                            'type' => 'report_approved',
                            'title' => 'New Student Report',
                            'message' => "A new {$report->report_type} report has been published for {$student->full_name}.",
                            'is_read' => false,
                            'meta' => [
                                'report_id' => $report->id,
                                'student_id' => $student->id,
                                'student_name' => $student->full_name,
                                'report_type' => $report->report_type,
                                'report_title' => $report->title,
                                'reviewer_id' => $user->id,
                                'reviewer_name' => $user->full_name,
                            ],
                            'sent_at' => now(),
                        ]);

                        // Send FCM notification to parent if they have FCM token and notifications enabled
                        if ($parent->notifications_enabled && $parent->fcm_token) {
                            $firebaseNotificationService->sendToToken(
                                $parent->fcm_token,
                                'New Student Report',
                                "A new {$report->report_type} report has been published for {$student->full_name}.",
                                [
                                    'type' => 'report_approved',
                                    'report_id' => (string)$report->id,
                                    'student_name' => $student->full_name,
                                    'report_type' => $report->report_type,
                                ]
                            );
                        }
                    }
                }

                // Also notify the teacher about the approval
                $teacher = User::find($report->teacher_id);
                
                if ($teacher) {
                    NotificationLog::create([
                        'user_id' => $report->teacher_id,
                        'type' => 'report_approved',
                        'title' => 'Student Report Approved',
                        'message' => "The report you submitted for " . (isset($student->full_name) ? $student->full_name : 'student') . " has been approved.",
                        'is_read' => false,
                        'meta' => [
                            'report_id' => $report->id,
                            'student_id' => $report->student_id,
                            'student_name' => isset($student->full_name) ? $student->full_name : 'Unknown',
                            'reviewer_id' => $user->id,
                            'reviewer_name' => $user->full_name,
                        ],
                        'sent_at' => now(),
                    ]);

                    // Send FCM notification to teacher if they have FCM token and notifications enabled
                    if ($teacher->notifications_enabled && $teacher->fcm_token) {
                        $firebaseNotificationService->sendToToken(
                            $teacher->fcm_token,
                            'Student Report Approved',
                            "The report you submitted for {$student->full_name} has been approved.",
                            [
                                'type' => 'report_approved',
                                'report_id' => (string)$report->id,
                                'student_name' => isset($student->full_name) ? $student->full_name : 'Unknown',
                            ]
                        );
                    }
                }
            }

            return $this->successResponse(new StudentReportResource($report), "Report {$report->status} successfully");
        } catch(Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function destroy($id)
    {
        try {
            $user = auth()->user();
            $report = StudentReport::where('institution_id', $user->institution_id)->findOrFail($id);

            // Only teacher who created it, or admin/principal can delete
            if (!$user->isRole(UserRole::Principal) && !$user->isRole(UserRole::SchoolAdmin) && $report->teacher_id !== $user->id) {
                return $this->errorResponse('Unauthorized to delete this report.', 403);
            }

            if ($report->file_path) {
                Storage::disk('public')->delete($report->file_path);
            }

            $report->delete();

            return $this->successResponse(null, 'Report deleted successfully');
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }
}
