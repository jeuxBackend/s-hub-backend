<?php

namespace App\Http\Controllers\Api\Principal;

use App\Http\Controllers\Controller;
use App\Http\Resources\StudentPreregistrationResource;
use App\Models\Classroom;
use App\Models\NotificationLog;
use App\Models\Student;
use App\Models\StudentPreregistration;
use App\Services\FirebaseNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class StudentPromotionController extends Controller
{
    public function index(Request $request)
    {
        try {
            $filters = $request->validate([
                'status' => ['nullable', 'in:submitted,approved,rejected'],
                'academic_year' => ['nullable', 'string', 'max:255'],
                'current_classroom_id' => ['nullable', 'exists:classrooms,id'],
                'target_classroom_id' => ['nullable', 'exists:classrooms,id'],
                'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            ]);

            $query = StudentPreregistration::query()
                ->with(['student', 'guardian', 'currentClassroom', 'targetClassroom'])
                ->whereHas('currentClassroom', fn($q) => $q->where('institution_id', auth()->user()->institution_id))
                ->whereHas('targetClassroom', fn($q) => $q->where('institution_id', auth()->user()->institution_id))
                ->latest();

            foreach (['status', 'academic_year', 'current_classroom_id', 'target_classroom_id'] as $field) {
                if (!empty($filters[$field])) {
                    $query->where($field, $filters[$field]);
                }
            }

            $paginator = $query->paginate($filters['per_page'] ?? 15);

            return $this->paginatedResponse(
                StudentPreregistrationResource::collection($paginator),
                'Student promotion records fetched successfully'
            );
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function store(Request $request, Student $student, FirebaseNotificationService $firebaseNotificationService)
    {
        try {
            $validated = $request->validate([
                'target_classroom_id' => ['required', 'exists:classrooms,id'],
                'notes' => ['nullable', 'string'],
            ]);

            $principal = auth()->user();

            if ($student->institution_id !== $principal->institution_id) {
                return $this->errorResponse('Unauthorized access to this student.', 403);
            }

            $currentClassroom = Classroom::where('institution_id', $principal->institution_id)
                ->findOrFail($student->classroom_id);

            if ((int) $validated['target_classroom_id'] === (int) $currentClassroom->id) {
                return $this->errorResponse('Target classroom must be different from the current classroom.', 422);
            }

            $targetClassroom = Classroom::where('institution_id', $principal->institution_id)
                ->findOrFail($validated['target_classroom_id']);

            $student->loadMissing(['studentGrades', 'classroom', 'guardian']);
            $eligibility = $student->promotionEligibility();
            $nextAcademicYear = $this->getNextAcademicYear();

            if (!$eligibility['eligible']) {
                return $this->errorResponse($eligibility['reason'], 422);
            }

            $existingPromotion = StudentPreregistration::where([
                'student_id' => $student->id,
                'current_classroom_id' => $currentClassroom->id,
                'target_classroom_id' => $targetClassroom->id,
                'academic_year' => $nextAcademicYear,
            ])->first();

            if ($existingPromotion && $existingPromotion->status === 'approved') {
                return $this->successResponse([
                    'current_classroom_id' => $currentClassroom->id,
                    'current_classroom_name' => $currentClassroom->name,
                    'target_classroom_id' => $targetClassroom->id,
                    'target_classroom_name' => $targetClassroom->name,
                    'academic_year' => $nextAcademicYear,
                    'promotion_id' => $existingPromotion->id,
                    'can_promote' => false,
                    'promotion_sent' => true,
                    'promotion_status' => 'approved',
                    'notified_parent' => false,
                ], 'This student has already been approved for promotion.');
            }

            if ($existingPromotion && $existingPromotion->status === 'submitted') {
                return $this->successResponse([
                    'current_classroom_id' => $currentClassroom->id,
                    'current_classroom_name' => $currentClassroom->name,
                    'target_classroom_id' => $targetClassroom->id,
                    'target_classroom_name' => $targetClassroom->name,
                    'academic_year' => $nextAcademicYear,
                    'promotion_id' => $existingPromotion->id,
                    'can_promote' => false,
                    'promotion_sent' => true,
                    'promotion_status' => 'submitted',
                    'notified_parent' => false,
                ], 'This student promotion request has already been sent.');
            }

            $preregistration = DB::transaction(function () use ($student, $currentClassroom, $targetClassroom, $validated, $existingPromotion, $nextAcademicYear) {
                return StudentPreregistration::updateOrCreate(
                    [
                        'student_id' => $student->id,
                        'current_classroom_id' => $currentClassroom->id,
                        'target_classroom_id' => $targetClassroom->id,
                        'academic_year' => $nextAcademicYear,
                    ],
                    [
                        'guardian_id' => $student->guardian_id,
                        'status' => 'submitted',
                        'submitted_at' => now(),
                        'approved_at' => null,
                        'notes' => $validated['notes'] ?? null,
                    ]
                );
            });

            $title = 'Class Promotion Confirmation';
            $message = "Your child {$student->full_name} is eligible for promotion from {$currentClassroom->name} to {$targetClassroom->name}. Please confirm if you want to continue their journey in our institute.";

            $notifiedParent = false;
            if (!($existingPromotion && $existingPromotion->status === 'submitted')) {
                $notification = NotificationLog::create([
                    'user_id' => $student->guardian_id,
                    'student_id' => $student->id,
                    'type' => 'student_promotion',
                    'title' => $title,
                    'message' => $message,
                    'is_read' => false,
                    'meta' => [
                        'promotion_id' => $preregistration->id,
                        'student_id' => $student->id,
                        'student_name' => $student->full_name,
                        'current_classroom_id' => $currentClassroom->id,
                        'current_classroom_name' => $currentClassroom->name,
                        'target_classroom_id' => $targetClassroom->id,
                        'target_classroom_name' => $targetClassroom->name,
                        'academic_year' => $nextAcademicYear,
                        'action' => 'confirm_promotion',
                    ],
                ]);

                if ($student->guardian?->notifications_enabled && $student->guardian?->fcm_token) {
                    $sent = $firebaseNotificationService->sendToToken(
                        $student->guardian->fcm_token,
                        $title,
                        $message,
                        [
                            'type' => 'student_promotion',
                            'promotion_id' => (string) $preregistration->id,
                            'student_id' => (string) $student->id,
                            'current_classroom_id' => (string) $currentClassroom->id,
                            'target_classroom_id' => (string) $targetClassroom->id,
                            'academic_year' => $nextAcademicYear,
                            'action' => 'confirm_promotion',
                        ]
                    );

                    $notifiedParent = (bool) $sent;
                }
            }

            return $this->successResponse([
                'current_classroom_id' => $currentClassroom->id,
                'current_classroom_name' => $currentClassroom->name,
                'target_classroom_id' => $targetClassroom->id,
                'target_classroom_name' => $targetClassroom->name,
                'academic_year' => $nextAcademicYear,
                'promotion_id' => $preregistration->id,
                'can_promote' => false,
                'promotion_sent' => true,
                'promotion_status' => 'submitted',
                'notified_parent' => $notifiedParent,
            ], 'Student promotion request sent successfully');
        } catch (Throwable $e) {
            Log::error('Failed to create class promotion batch', [
                'error' => $e->getMessage(),
            ]);

            return $this->exceptionResponse($e);
        }
    }

    private function getNextAcademicYear(): string
    {
        $currentYear = (int) date('Y');

        return $currentYear . '-' . ($currentYear + 1);
    }
}
