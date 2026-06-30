<?php

namespace App\Services;

use App\Events\NewNotificationEvent;
use App\Events\SchoolAlertBroadcastedEvent;
use App\Models\Institution;
use App\Models\NotificationLog;
use App\Models\SchoolAlert;
use App\Models\SchoolAlertResponse;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class SchoolAlertService
{
    public function __construct(
        protected FirebaseNotificationService $firebaseNotificationService
    ) {
    }

    public function listActiveAlertsForUser(User $user): Collection
    {
        return SchoolAlert::with(['responses.user', 'responses.student', 'creator', 'confirmedBy', 'resolvedBy'])
            ->where('institution_id', $user->institution_id)
            ->whereIn('status', ['potential', 'active'])
            ->orderByDesc('id')
            ->get();
    }

    public function triggerAbduction(User $actor, array $data = []): SchoolAlert
    {
        return DB::transaction(function () use ($actor, $data) {
            $this->ensureAlertFeatureEnabled($actor->institution_id);
            $this->ensureNoDuplicateActiveAlert($actor->institution_id, 'abduction');

            $alert = SchoolAlert::create([
                'institution_id' => $actor->institution_id,
                'created_by' => $actor->id,
                'type' => 'abduction',
                'status' => 'potential',
                'title' => $data['title'] ?? 'Potential Abduction Alert',
                'message' => $data['message'] ?? 'A teacher has raised a potential abduction alert.',
                'confirmation_count' => 1,
                'meta' => array_merge($data['meta'] ?? [], [
                    'ring_duration_seconds' => 180,
                    'triggered_role' => $actor->role?->value ?? $actor->role,
                ]),
            ]);

            $this->dispatchAlertNotification($alert, $actor, 'potential');
            event(new SchoolAlertBroadcastedEvent($alert->fresh(['responses.user', 'responses.student'])));

            return $alert->fresh(['responses.user', 'responses.student', 'creator']);
        });
    }

    public function confirmAbduction(SchoolAlert $alert, User $actor): SchoolAlert
    {
        return DB::transaction(function () use ($alert, $actor) {
            $this->ensureSameInstitution($alert, $actor);
            $this->ensureAlertFeatureEnabled($actor->institution_id);

            if ($alert->type !== 'abduction') {
                abort(422, 'Only abduction alerts can be confirmed.');
            }

            if ($alert->status !== 'potential') {
                abort(422, 'This alert is no longer waiting for confirmation.');
            }

            if ((int) $alert->created_by === (int) $actor->id) {
                abort(422, 'The same teacher who triggered the alert cannot confirm it.');
            }

            $alert->update([
                'status' => 'active',
                'confirmed_by' => $actor->id,
                'confirmed_at' => now(),
                'confirmation_count' => max(2, (int) $alert->confirmation_count + 1),
            ]);

            $alert->load(['responses.user', 'responses.student', 'creator', 'confirmedBy']);
            $this->dispatchAlertNotification($alert, $actor, 'active');
            event(new SchoolAlertBroadcastedEvent($alert));

            return $alert;
        });
    }

    public function triggerEmergency(User $actor, array $data = []): SchoolAlert
    {
        return DB::transaction(function () use ($actor, $data) {
            $this->ensureAlertFeatureEnabled($actor->institution_id);
            $this->ensureNoDuplicateActiveAlert($actor->institution_id, 'emergency');

            $alert = SchoolAlert::create([
                'institution_id' => $actor->institution_id,
                'created_by' => $actor->id,
                'confirmed_by' => $actor->id,
                'confirmed_at' => now(),
                'type' => 'emergency',
                'status' => 'active',
                'title' => $data['title'] ?? 'Emergency Alert',
                'message' => $data['message'] ?? 'An emergency alert has been activated.',
                'confirmation_count' => 1,
                'meta' => array_merge($data['meta'] ?? [], [
                    'ring_duration_seconds' => 180,
                    'triggered_role' => $actor->role?->value ?? $actor->role,
                ]),
            ]);

            $this->dispatchAlertNotification($alert, $actor, 'active');
            event(new SchoolAlertBroadcastedEvent($alert->fresh(['responses.user', 'responses.student'])));

            return $alert->fresh(['responses.user', 'responses.student', 'creator', 'confirmedBy']);
        });
    }

    public function resolveAlert(SchoolAlert $alert, User $actor, array $data = []): SchoolAlert
    {
        return DB::transaction(function () use ($alert, $actor, $data) {
            $this->ensureSameInstitution($alert, $actor);

            if (!in_array($actor->role?->value ?? $actor->role, ['principal', 'school-admin', 'admin', 'sub_admin'], true)) {
                abort(403, 'Unauthorized to resolve this alert.');
            }

            $alert->update([
                'status' => 'resolved',
                'resolved_by' => $actor->id,
                'resolved_at' => now(),
                'meta' => array_merge($alert->meta ?? [], $data['meta'] ?? []),
            ]);

            event(new SchoolAlertBroadcastedEvent($alert->fresh(['responses.user', 'responses.student', 'creator', 'confirmedBy', 'resolvedBy'])));

            return $alert->fresh(['responses.user', 'responses.student', 'creator', 'confirmedBy', 'resolvedBy']);
        });
    }

    public function recordResponse(SchoolAlert $alert, User $actor, array $data): SchoolAlertResponse
    {
        $this->ensureSameInstitution($alert, $actor);

        if ($actor->role?->value === 'parent' && empty($data['student_id'])) {
            abort(422, 'student_id is required for parent responses.');
        }

        $student = null;
        if (!empty($data['student_id'])) {
            $studentQuery = Student::whereKey($data['student_id'])
                ->where('institution_id', $actor->institution_id);

            if ($actor->role?->value === 'parent') {
                $studentQuery->where('guardian_id', $actor->id);
            }

            $student = $studentQuery->first();

            if (!$student) {
                abort(403, 'Unauthorized student selection for this alert response.');
            }
        }

        $response = SchoolAlertResponse::create([
            'school_alert_id' => $alert->id,
            'institution_id' => $actor->institution_id,
            'user_id' => $actor->id,
            'student_id' => $student?->id,
            'source_role' => $actor->role?->value ?? $actor->role,
            'response_type' => $data['response_type'],
            'note' => $data['note'] ?? null,
            'meta' => $data['meta'] ?? null,
            'responded_at' => now(),
        ]);

        $alert->load(['responses.user', 'responses.student', 'creator', 'confirmedBy', 'resolvedBy']);
        event(new SchoolAlertBroadcastedEvent($alert));

        return $response;
    }

    protected function dispatchAlertNotification(SchoolAlert $alert, User $actor, string $state): void
    {
        $targetRoles = $state === 'potential'
            ? ['teacher', 'school-admin', 'principal']
            : ['teacher', 'school-admin', 'principal', 'parent'];

        $recipients = User::query()
            ->where('institution_id', $alert->institution_id)
            ->whereIn('role', $targetRoles)
            ->get();

        $title = $state === 'potential'
            ? 'Potential Abduction Alert'
            : ($alert->type === 'emergency' ? 'Emergency Alert' : 'Abduction Alert Activated');

        $message = $alert->message ?: $title;

        foreach ($recipients as $recipient) {
            $notification = NotificationLog::create([
                'user_id' => $recipient->id,
                'type' => 'school_alert',
                'title' => $title,
                'message' => $message,
                'is_read' => false,
                'meta' => [
                    'alert_id' => $alert->id,
                    'school_id' => $alert->institution_id,
                    'alert_type' => $alert->type,
                    'alert_status' => $alert->status,
                    'state' => $state,
                    'triggered_by' => $actor->id,
                    'ring' => true,
                    'ring_duration_seconds' => 180,
                ],
            ]);

            event(new NewNotificationEvent($notification));

            if (!$recipient->notifications_enabled || !$recipient->fcm_token) {
                continue;
            }

            try {
                $this->firebaseNotificationService->sendToToken(
                    $recipient->fcm_token,
                    $title,
                    $message,
                    [
                        'type' => 'school_alert',
                        'alert_id' => $alert->id,
                        'school_id' => $alert->institution_id,
                        'alert_type' => $alert->type,
                        'alert_status' => $alert->status,
                        'state' => $state,
                        'ring' => true,
                        'ring_duration_seconds' => 180,
                    ]
                );
            } catch (Throwable $e) {
                Log::error('Failed to send school alert push notification', [
                    'alert_id' => $alert->id,
                    'user_id' => $recipient->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    protected function ensureAlertFeatureEnabled(int|string|null $institutionId): void
    {
        $institution = Institution::find($institutionId);

        if (!$institution || !$institution->alert_feature_enabled) {
            abort(403, 'Alert feature is not enabled for this school.');
        }
    }

    protected function ensureNoDuplicateActiveAlert(int|string|null $institutionId, string $type): void
    {
        $exists = SchoolAlert::where('institution_id', $institutionId)
            ->where('type', $type)
            ->whereIn('status', ['potential', 'active'])
            ->exists();

        if ($exists) {
            abort(422, 'There is already an active or pending alert of this type for this school.');
        }
    }

    protected function ensureSameInstitution(SchoolAlert $alert, User $actor): void
    {
        if ((int) $alert->institution_id !== (int) $actor->institution_id) {
            abort(403, 'Unauthorized access to this alert.');
        }
    }
}
