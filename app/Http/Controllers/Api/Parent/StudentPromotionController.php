<?php

namespace App\Http\Controllers\Api\Parent;

use App\Http\Controllers\Controller;
use App\Http\Resources\StudentPreregistrationResource;
use App\Models\Classroom;
use App\Models\NotificationLog;
use App\Models\StudentPreregistration;
use App\Services\FirebaseNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class StudentPromotionController extends Controller
{
    public function index(Request $request)
    {
        try {
            $filters = $request->validate([
                'status' => ['nullable', 'in:submitted,approved,rejected'],
                'academic_year' => ['nullable', 'string', 'max:255'],
                'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            ]);

            $query = StudentPreregistration::query()
                ->with(['student', 'guardian', 'currentClassroom', 'targetClassroom'])
                ->where('guardian_id', auth()->id())
                ->latest();

            foreach (['status', 'academic_year'] as $field) {
                if (!empty($filters[$field])) {
                    $query->where($field, $filters[$field]);
                }
            }

            $paginator = $query->paginate($filters['per_page'] ?? 15);

            return $this->paginatedResponse(
                StudentPreregistrationResource::collection($paginator),
                'Promotion requests fetched successfully'
            );
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function approve(Request $request, $id, FirebaseNotificationService $firebaseNotificationService)
    {
        try {
            $validated = $request->validate([
                'notes' => ['nullable', 'string'],
            ]);

            $preregistration = StudentPreregistration::with(['student', 'guardian', 'currentClassroom', 'targetClassroom'])
                ->where('guardian_id', auth()->id())
                ->findOrFail($id);

            if ($preregistration->status !== 'submitted') {
                return $this->errorResponse('Only submitted promotion requests can be approved.', 422);
            }

            $student = $preregistration->student;
            $targetClassroom = Classroom::where('institution_id', $student->institution_id)
                ->findOrFail($preregistration->target_classroom_id);

            DB::transaction(function () use ($preregistration, $student, $targetClassroom, $validated) {
                $preregistration->update([
                    'status' => 'approved',
                    'approved_at' => now(),
                    'notes' => $validated['notes'] ?? $preregistration->notes,
                ]);

                $student->update([
                    'classroom_id' => $targetClassroom->id,
                ]);
            });

            $preregistration->load(['student', 'guardian', 'currentClassroom', 'targetClassroom']);

            $this->notifyPrincipal(
                $preregistration,
                'Promotion Approved',
                "The promotion for {$student->full_name} has been approved by the parent.",
                $firebaseNotificationService
            );

            return $this->successResponse(
                new StudentPreregistrationResource($preregistration),
                'Promotion approved successfully'
            );
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function reject(Request $request, $id, FirebaseNotificationService $firebaseNotificationService)
    {
        try {
            $validated = $request->validate([
                'notes' => ['nullable', 'string'],
            ]);

            $preregistration = StudentPreregistration::with(['student', 'guardian', 'currentClassroom', 'targetClassroom'])
                ->where('guardian_id', auth()->id())
                ->findOrFail($id);

            if ($preregistration->status !== 'submitted') {
                return $this->errorResponse('Only submitted promotion requests can be rejected.', 422);
            }

            $preregistration->update([
                'status' => 'rejected',
                'notes' => $validated['notes'] ?? $preregistration->notes,
            ]);

            $preregistration->load(['student', 'guardian', 'currentClassroom', 'targetClassroom']);

            $this->notifyPrincipal(
                $preregistration,
                'Promotion Rejected',
                "The promotion for {$preregistration->student->full_name} has been rejected by the parent.",
                $firebaseNotificationService
            );

            return $this->successResponse(
                new StudentPreregistrationResource($preregistration),
                'Promotion rejected successfully'
            );
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    private function notifyPrincipal(
        StudentPreregistration $preregistration,
        string $title,
        string $message,
        FirebaseNotificationService $firebaseNotificationService
    ): void {
        $student = $preregistration->student;
        $principal = $student?->institution?->principal;

        if (!$principal) {
            return;
        }

        NotificationLog::create([
            'user_id' => $principal->id,
            'student_id' => $student->id,
            'type' => 'student_promotion_review',
            'title' => $title,
            'message' => $message,
            'is_read' => false,
            'meta' => [
                'promotion_id' => $preregistration->id,
                'student_id' => $student->id,
                'student_name' => $student->full_name,
                'current_classroom_id' => $preregistration->current_classroom_id,
                'current_classroom_name' => $preregistration->currentClassroom?->name,
                'target_classroom_id' => $preregistration->target_classroom_id,
                'target_classroom_name' => $preregistration->targetClassroom?->name,
                'academic_year' => $preregistration->academic_year,
                'promotion_status' => $preregistration->status,
            ],
        ]);

        if ($principal->notifications_enabled && $principal->fcm_token) {
            $firebaseNotificationService->sendToToken(
                $principal->fcm_token,
                $title,
                $message,
                [
                    'type' => 'student_promotion_review',
                    'promotion_id' => (string) $preregistration->id,
                    'student_id' => (string) $student->id,
                    'current_classroom_id' => (string) $preregistration->current_classroom_id,
                    'target_classroom_id' => (string) $preregistration->target_classroom_id,
                    'academic_year' => (string) $preregistration->academic_year,
                    'promotion_status' => (string) $preregistration->status,
                ]
            );
        }
    }
}
