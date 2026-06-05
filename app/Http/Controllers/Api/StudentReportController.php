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
    public function store(Request $request)
    {
        try {
            $user = auth()->user();

            if (!$user->isRole(UserRole::Teacher)) {
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

            return $this->successResponse(new StudentReportResource($report), 'Report submitted for approval', 201);
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    // Principal/Admin approves or rejects a report
    public function updateStatus(Request $request, $id)
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
                NotificationLog::create([
                    'user_id' => $report->teacher_id,
                    'type' => 'report_rejected',
                    'title' => 'Student Report Rejected',
                    'message' => "The report you submitted for student ID {$report->student_id} was rejected." . ($request->reason ? " Reason: {$request->reason}" : " Please review and update."),
                    'is_read' => false,
                    'meta' => [
                        'report_id' => $report->id,
                    ],
                    'sent_at' => now(),
                ]);
            } elseif ($report->status == 'approved') {
                // If you want to notify the parent when it's approved
                $student = $report->student;
                if ($student && $student->guardian_id) {
                    NotificationLog::create([
                        'user_id' => $student->guardian_id,
                        'type' => 'report_approved',
                        'title' => 'New Student Report',
                        'message' => "A new {$report->report_type} report has been published for {$student->full_name}.",
                        'is_read' => false,
                        'meta' => [
                            'report_id' => $report->id,
                            'student_id' => $student->id,
                        ],
                        'sent_at' => now(),
                    ]);
                }
            }

            return $this->successResponse(new StudentReportResource($report), "Report {$report->status} successfully");
        } catch (Throwable $e) {
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
