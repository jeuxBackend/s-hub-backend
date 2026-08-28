<?php

namespace App\Services;

use App\Events\NewNotificationEvent;
use App\Events\SchoolAlertBroadcastedEvent;
use App\Models\Admin;
use App\Models\Institution;
use App\Models\NotificationLog;
use App\Models\SchoolAlert;
use App\Models\SchoolAlertResponse;
use App\Models\Student;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
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
            ->get()
            ->map(function (SchoolAlert $alert) {
                $alert->is_expired = $this->isExpiredForCurrentView($alert);

                return $alert;
            });

    }

    public function listParentCurrentAlerts(User $user): Collection
    {
        $alerts = SchoolAlert::with([
            'responses' => function ($query) use ($user) {
                $query->whereHas('student', function ($studentQuery) use ($user) {
                    $studentQuery->where('guardian_id', $user->id);
                })->with(['student.classroom', 'parentUser', 'schoolUser']);
            },
            'creator',
            'confirmedBy',
            'resolvedBy',
        ])
            ->where('institution_id', $user->institution_id)
            ->whereIn('status', ['potential', 'active'])
            ->orderByDesc('id')
            ->get();

        return $alerts->map(function (SchoolAlert $alert) {
            $alert->is_expired = $this->isExpiredForCurrentView($alert);
            $alert->setRelation(
                'responses',
                $alert->responses->map(function (SchoolAlertResponse $response) {
                    $response->setRelation('parentUser', null);
                    $response->setRelation('schoolUser', null);

                    return $response;
                })
            );

            return $alert;
        });
    }

    public function listResponsesForStaff(User|Admin $actor, array $filters = []): LengthAwarePaginator
    {
        $institutionId = $filters['institution_id'] ?? $actor->institution_id ?? null;

        if (!$institutionId) {
            abort(422, 'institution_id is required.');
        }

        $this->ensureSameInstitutionForList($institutionId, $actor);

        $query = SchoolAlertResponse::query()
            ->with(['parentUser', 'schoolUser', 'student.classroom', 'alert'])
            ->where('institution_id', $institutionId)
            ->orderByDesc('id');

        if (!empty($filters['alert_id'])) {
            $query->where('school_alert_id', $filters['alert_id']);
        }

        if (!empty($filters['student_id'])) {
            $query->where('student_id', $filters['student_id']);
        }

        if (!empty($filters['response_type'])) {
            $query->where(function ($subQuery) use ($filters) {
                $subQuery->where('parent_response_type', $filters['response_type'])
                    ->orWhere('school_response_type', $filters['response_type']);
            });
        }

        if (!empty($filters['parent_response_type'])) {
            $query->where('parent_response_type', $filters['parent_response_type']);
        }

        if (!empty($filters['school_response_type'])) {
            $query->where('school_response_type', $filters['school_response_type']);
        }

        if (!empty($filters['source_role'])) {
            $query->where('source_role', $filters['source_role']);
        }

        $perPage = (int) ($filters['per_page'] ?? 15);
        $perPage = max(1, min($perPage, 100));

        return $query->paginate($perPage)->withQueryString();
    }

    public function listResponsesForParent(User $user, array $filters = []): LengthAwarePaginator
    {
        if (($user->role?->value ?? $user->role) !== 'parent') {
            abort(403, 'This endpoint is only for parents.');
        }

        $institutionId = $user->institution_id;

        if (!$institutionId) {
            abort(422, 'institution_id is required.');
        }

        $query = SchoolAlertResponse::query()
            ->with(['parentUser', 'schoolUser', 'student.classroom', 'alert'])
            ->where('institution_id', $institutionId)
            ->whereHas('student', function ($studentQuery) use ($user) {
                $studentQuery->where('guardian_id', $user->id);
            })
            ->orderByDesc('id');

        if (!empty($filters['alert_id'])) {
            $query->where('school_alert_id', $filters['alert_id']);
        }

        if (!empty($filters['student_id'])) {
            $query->where('student_id', $filters['student_id']);
        }

        if (!empty($filters['parent_response_type'])) {
            $query->where('parent_response_type', $filters['parent_response_type']);
        }

        if (!empty($filters['school_response_type'])) {
            $query->where('school_response_type', $filters['school_response_type']);
        }

        $perPage = (int) ($filters['per_page'] ?? 15);
        $perPage = max(1, min($perPage, 100));

        return $query->paginate($perPage)->withQueryString();
    }

    public function triggerAbduction(User|Admin $actor, array $data = []): SchoolAlert
    {
        return DB::transaction(function () use ($actor, $data) {
            $institution = $this->resolveAlertInstitution($actor, $data);
            $this->ensureAlertFeatureEnabled($institution->id);
            return $this->triggerAlert($actor, $institution, 'abduction', $data);
        });
    }

    public function confirmAbduction(SchoolAlert $alert, User|Admin $actor): SchoolAlert
    {
        return $this->confirmAlert($alert, $actor);
    }

    public function triggerEmergency(User|Admin $actor, array $data = []): SchoolAlert
    {
        return DB::transaction(function () use ($actor, $data) {
            $institution = $this->resolveAlertInstitution($actor, $data);
            $this->ensureAlertFeatureEnabled($institution->id);
            return $this->triggerAlert($actor, $institution, 'emergency', $data);
        });
    }

    public function resolveAlert(SchoolAlert $alert, User|Admin $actor, array $data = []): SchoolAlert
    {
        $updatedAlert = DB::transaction(function () use ($alert, $actor, $data) {
            $this->ensureSameInstitution($alert, $actor);

            if (!in_array($actor->role?->value ?? $actor->role, ['principal', 'school-admin', 'admin', 'sub_admin'], true)) {
                abort(403, 'Unauthorized to resolve this alert.');
            }

            if ($alert->status === 'resolved') {
                abort(422, 'This alert has already been resolved.');
            }

            $alert->update([
                'status' => 'resolved',
                'resolved_by' => $actor->id,
                'resolved_at' => now(),
                'meta' => array_merge($alert->meta ?? [], $data['meta'] ?? []),
            ]);

            return $alert->fresh(['responses.user', 'responses.student', 'creator', 'confirmedBy', 'resolvedBy']);
        });

        try {
            $this->dispatchAlertResolutionNotification($updatedAlert, $actor);
            event(new SchoolAlertBroadcastedEvent($updatedAlert));
        } catch (Throwable $e) {
            Log::error('Failed to dispatch school alert resolution notifications', [
                'alert_id' => $updatedAlert->id,
                'institution_id' => $updatedAlert->institution_id,
                'error' => $e->getMessage(),
            ]);
        }

        return $updatedAlert;
    }

    public function recordResponse(SchoolAlert $alert, User|Admin $actor, array $data): SchoolAlertResponse
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

        $isParent = (($actor->role?->value ?? $actor->role) === 'parent');

        $response = SchoolAlertResponse::firstOrNew([
            'school_alert_id' => $alert->id,
            'student_id' => $student?->id,
        ]);

        if (!$response->exists) {
            $response->institution_id = $actor->institution_id;
            $response->user_id = $isParent ? $actor->id : ($student?->guardian_id ?? $actor->id);
            $response->source_role = $isParent ? 'parent' : 'school';
        }

        if ($isParent) {
            $response->parent_user_id = $actor->id;
            $response->parent_response_type = $data['response_type'];
            $response->note = $data['note'] ?? $response->note;
            $response->meta = $data['meta'] ?? $response->meta;
        } else {
            $response->school_user_id = $actor->id;
            $response->school_response_type = $data['response_type'];
            $response->note = $data['note'] ?? $response->note;
            $response->meta = $data['meta'] ?? $response->meta;
        }

        $response->responded_at = now();
        $response->save();

        $alert->load(['responses.user', 'responses.student', 'creator', 'confirmedBy', 'resolvedBy']);
        // event(new SchoolAlertBroadcastedEvent($alert));

        return $response;
    }

    /**
     * Record multiple parent responses in one request while storing one row per child.
     *
     * The request can optionally include:
     * - student_ids: array<int>
     * - response_types: array<string>
     * - notes: array<string|null>
     * - metas: array<array|null>
     *
     * If notes/metas are omitted, the shared note/meta fields are reused for each row.
     *
     * @return \Illuminate\Support\Collection<int, SchoolAlertResponse>
     */
    public function recordParentResponses(SchoolAlert $alert, User|Admin $actor, array $data): Collection
    {
        $this->ensureSameInstitution($alert, $actor);

        if (($actor->role?->value ?? $actor->role) !== 'parent') {
            abort(403, 'This endpoint is only for parent responses.');
        }

        $studentIds = array_values($data['student_ids'] ?? []);
        $responseTypes = array_values($data['response_types'] ?? []);
        $notes = array_values($data['notes'] ?? []);
        $metas = array_values($data['metas'] ?? []);
        $sharedNote = $data['note'] ?? null;
        $sharedMeta = $data['meta'] ?? null;

        if (count($studentIds) !== count($responseTypes)) {
            abort(422, 'student_ids and response_types must have the same number of items.');
        }

        return DB::transaction(function () use ($alert, $actor, $studentIds, $responseTypes, $notes, $metas, $sharedNote, $sharedMeta) {
            $responses = collect();

            foreach ($studentIds as $index => $studentId) {
                $studentQuery = Student::whereKey($studentId)
                    ->where('institution_id', $actor->institution_id)
                    ->where('guardian_id', $actor->id);

                $student = $studentQuery->first();

                if (!$student) {
                    abort(403, 'Unauthorized student selection for this alert response.');
                }

                $response = SchoolAlertResponse::firstOrNew([
                    'school_alert_id' => $alert->id,
                    'student_id' => $student->id,
                ]);

                if (!$response->exists) {
                    $response->institution_id = $actor->institution_id;
                    $response->user_id = $actor->id;
                    $response->source_role = 'parent';
                }

                $response->parent_user_id = $actor->id;
                $response->parent_response_type = $responseTypes[$index];
                $response->note = $notes[$index] ?? $sharedNote ?? $response->note;
                $response->meta = $metas[$index] ?? $sharedMeta ?? $response->meta;
                $response->responded_at = now();
                $response->save();

                $responses->push($response);
            }

            $alert->load(['responses.user', 'responses.student', 'creator', 'confirmedBy', 'resolvedBy']);
            // event(new SchoolAlertBroadcastedEvent($alert));

            return $responses;
        });
    }

    protected function dispatchAlertNotification(SchoolAlert $alert, User $actor, string $state): void
    {
        $targetRoles = $state === 'potential'
            ? ['teacher', 'school-admin', 'principal']
            : ['teacher', 'school-admin', 'principal', 'parent'];

        $requiresConfirmation = $state === 'potential';
        $alertTypeLabel = ucfirst($alert->type);

        $recipients = $this->filterRecipientsForAlert(
            User::query()
                ->where('institution_id', $alert->institution_id)
                ->whereIn('role', $targetRoles)
                ->when($requiresConfirmation, function ($query) use ($alert) {
                    $query->where('id', '!=', $alert->created_by);
                })
                ->get(),
            $alert
        );

        $title = $requiresConfirmation
            ? "Confirm {$alertTypeLabel} Alert"
            : ($alert->type === 'emergency' ? 'Emergency Alert' : 'Abduction Alert Activated');

        $message = $requiresConfirmation
            ? "A potential {$alert->type} alert requires confirmation from another teacher, school-admin, or principal."
            : ($alert->message ?: $title);

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
                        'action' => $requiresConfirmation ? 'confirm_alert' : $state,
                        'requires_confirmation' => $requiresConfirmation,
                        'exclude_user_id' => $requiresConfirmation ? $alert->created_by : null,
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

    protected function dispatchAlertResolutionNotification(SchoolAlert $alert, User|Admin $actor): void
    {
        $recipients = $this->filterRecipientsForAlert(
            User::query()
                ->where('institution_id', $alert->institution_id)
                ->whereIn('role', ['teacher', 'school-admin', 'principal', 'parent'])
                ->get(),
            $alert
        );

        $resolverName = $actor->full_name
            ?? trim(($actor->first_name ?? '') . ' ' . ($actor->last_name ?? '') . ' ' . ($actor->sure_name ?? ''));

        $title = 'School Alert Resolved';
        $message = sprintf(
            '%s alert "%s" has been resolved by %s.',
            ucfirst($alert->type),
            $alert->title,
            $resolverName
        );

        foreach ($recipients as $recipient) {
            $notification = $this->createResolutionNotificationLog($recipient, $alert, $actor, $title, $message);

            if (!$notification) {
                continue;
            }

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
                        'type' => 'school_alert_resolved',
                        'alert_id' => $alert->id,
                        'school_id' => $alert->institution_id,
                        'alert_type' => $alert->type,
                        'alert_status' => $alert->status,
                        'state' => 'resolved',
                        'resolved_by' => $actor->id,
                    ]
                );
            } catch (Throwable $e) {
                Log::error('Failed to send school alert resolution push notification', [
                    'alert_id' => $alert->id,
                    'user_id' => $recipient->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    protected function filterRecipientsForAlert(Collection $recipients, SchoolAlert $alert): Collection
    {
        return $recipients
            ->filter(fn (User $recipient) => $this->shouldReceiveAlertNotification($recipient, $alert))
            ->values();
    }

    protected function shouldReceiveAlertNotification(User $recipient, SchoolAlert $alert): bool
    {
        $role = $recipient->role?->value ?? $recipient->role;

        if ($role !== 'parent') {
            return true;
        }

        if ($alert->type !== 'emergency') {
            return true;
        }

        return (bool) ($recipient->allow_alert ?? true);
    }

    protected function createResolutionNotificationLog(User $recipient, SchoolAlert $alert, User|Admin $actor, string $title, string $message): ?NotificationLog
    {
        $payload = [
            'user_id' => $recipient->id,
            'student_id' => null,
            'type' => 'school_alert_resolved',
            'title' => $title,
            'message' => $message,
            'is_read' => false,
            'is_expired' => false,
            'attendance_request_key' => null,
            'meta' => [
                'alert_id' => $alert->id,
                'school_id' => $alert->institution_id,
                'alert_type' => $alert->type,
                'alert_status' => $alert->status,
                'state' => 'resolved',
                'resolved_by' => $actor->id,
                'resolved_at' => $alert->resolved_at?->toISOString(),
            ],
            'sent_at' => now($recipient->timezone ?? config('app.timezone', 'UTC')),
        ];

        try {
            return NotificationLog::create($payload);
        } catch (Throwable $e) {
            Log::warning('Eloquent notification log create failed for alert resolution, using direct insert fallback', [
                'alert_id' => $alert->id,
                'user_id' => $recipient->id,
                'error' => $e->getMessage(),
            ]);

            $id = DB::table('notification_logs')->insertGetId([
                'user_id' => $payload['user_id'],
                'student_id' => $payload['student_id'],
                'type' => $payload['type'],
                'title' => $payload['title'],
                'message' => $payload['message'],
                'is_read' => $payload['is_read'],
                'is_expired' => $payload['is_expired'],
                'attendance_request_key' => $payload['attendance_request_key'],
                'meta' => json_encode($payload['meta']),
                'sent_at' => $payload['sent_at'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return NotificationLog::find($id);
        }
    }

    protected function ensureAlertFeatureEnabled(int|string|null $institutionId): void
    {
        $institution = Institution::find($institutionId);

        if (!$institution || !$institution->alert_feature_enabled) {
            abort(403, 'Alert feature is not enabled for this school.');
        }
    }

    protected function ensureAlertTypeAllowed(int|string|null $institutionId, string $type): void
    {
        $institution = Institution::find($institutionId);
        $allowedTypes = $institution?->allowed_alert_types ?? [];

        if (empty($allowedTypes)) {
            $allowedTypes = ['abduction', 'emergency'];
        }

        if (!in_array($type, $allowedTypes, true)) {
            abort(403, ucfirst($type) . ' alerts are not enabled for this school.');
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

    protected function ensureSameInstitution(SchoolAlert $alert, User|Admin $actor): void
    {
        $actorInstitutionId = $actor->institution_id ?? null;

        if ((int) $alert->institution_id !== (int) $actorInstitutionId) {
            abort(403, 'Unauthorized access to this alert.');
        }
    }

    protected function ensureSameInstitutionForList(int|string $institutionId, User|Admin $actor): void
    {
        $actorInstitutionId = $actor->institution_id ?? null;

        if ($actorInstitutionId && (int) $actorInstitutionId !== (int) $institutionId) {
            abort(403, 'This institution does not match your account.');
        }

        if (($actor->role?->value ?? $actor->role) === 'admin' && !empty($actor->institutions) && !$actor->institutions->contains('id', $institutionId)) {
            abort(403, 'This institution does not belong to your managed schools.');
        }
    }

    protected function triggerAlert(User|Admin $actor, Institution $institution, string $type, array $data = []): SchoolAlert
    {
        $this->ensureAlertTypeAllowed($institution->id, $type);
        $this->ensureNoDuplicateActiveAlert($institution->id, $type);

        $isPrincipal = $this->isPrincipalOrPlatformAdmin($actor);
        $initialStatus = $isPrincipal ? 'active' : 'potential';

        $alert = SchoolAlert::create([
            'institution_id' => $institution->id,
            'created_by' => $actor->id,
            'type' => $type,
            'status' => $initialStatus,
            'title' => $data['title'] ?? ($type === 'abduction' ? 'Potential Abduction Alert' : 'Emergency Alert'),
            'message' => $data['message'] ?? (
                $type === 'abduction'
                ? 'A school staff member has raised a potential abduction alert.'
                : 'A school staff member has activated an emergency alert.'
            ),
            'confirmation_count' => $isPrincipal ? 1 : 0,
            'meta' => array_merge($data['meta'] ?? [], [
                'ring_duration_seconds' => 180,
                'triggered_role' => $actor->role?->value ?? $actor->role,
            ]),
        ]);

        if ($isPrincipal) {
            $alert->update([
                'confirmed_by' => $actor->id,
                'confirmed_at' => now(),
            ]);
        }

        $this->dispatchAlertNotification($alert, $actor, $initialStatus);
        event(new SchoolAlertBroadcastedEvent($alert->fresh(['responses.user', 'responses.student'])));

        return $alert->fresh(['responses.user', 'responses.student', 'creator', 'confirmedBy']);
    }

    protected function resolveAlertInstitution(User|Admin $actor, array $data): Institution
    {
        if ($actor instanceof Admin) {
            $institutionId = $data['institution_id'] ?? null;

            if (!$institutionId) {
                abort(422, 'institution_id is required for platform admins.');
            }

            return Institution::findOrFail($institutionId);
        }

        $institutionId = $actor->institution_id;

        if (!$institutionId) {
            abort(403, 'No institution is associated with this account.');
        }

        if (!empty($data['institution_id']) && (int) $data['institution_id'] !== (int) $institutionId) {
            abort(403, 'You can only initiate alerts for your own institution.');
        }

        return Institution::findOrFail($institutionId);
    }

    protected function isPrincipalOrPlatformAdmin(User|Admin $actor): bool
    {
        if ($actor instanceof Admin) {
            return in_array($actor->role?->value ?? $actor->role, ['admin', 'sub_admin'], true);
        }

        return $actor->role?->value === 'principal';
    }

    public function confirmAlert(SchoolAlert $alert, User $actor): SchoolAlert
    {
        return DB::transaction(function () use ($alert, $actor) {
            $this->ensureSameInstitution($alert, $actor);
            $this->ensureAlertFeatureEnabled($actor->institution_id);

            if ($alert->status !== 'potential') {
                abort(422, 'This alert is no longer waiting for confirmation.');
            }

            if ((int) $alert->created_by === (int) $actor->id) {
                abort(422, 'The same staff member who triggered the alert cannot confirm it.');
            }

            $alert->update([
                'status' => 'active',
                'confirmed_by' => $actor->id,
                'confirmed_at' => now(),
                'confirmation_count' => max(1, (int) $alert->confirmation_count + 1),
            ]);

            $alert->load(['responses.user', 'responses.student', 'creator', 'confirmedBy']);
            $this->dispatchAlertNotification($alert, $actor, 'active');
            event(new SchoolAlertBroadcastedEvent($alert));

            return $alert;
        });
    }

    protected function isExpiredForCurrentView(SchoolAlert $alert): bool
    {
        if ($alert->type !== 'abduction') {
            return false;
        }

        if ($alert->status === 'resolved') {
            return false;
        }

        if (!$alert->created_at) {
            return false;
        }

        return Carbon::parse($alert->created_at)->addMinutes(SchoolAlert::ABDUCTION_EXPIRY_MINUTES)->isPast();
    }
}
