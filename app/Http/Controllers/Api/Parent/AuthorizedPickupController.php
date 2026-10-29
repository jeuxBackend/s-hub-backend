<?php

namespace App\Http\Controllers\Api\Parent;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\AuthorizedPickupResource;
use App\Models\AuthorizedPickup;
use App\Models\NotificationLog;
use App\Models\Student;
use App\Models\User;
use App\Services\FirebaseNotificationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Throwable;

class AuthorizedPickupController extends Controller
{
    public function index()
    {
        try {
            $authorizedPickup = auth()->user()->authorizedPickup;

            return $this->successResponse(
                $authorizedPickup ? new AuthorizedPickupResource($authorizedPickup) : null,
                'Current authorized pickup retrieved successfully'
            );
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    /**
     * GET /parent/authorized-pickup/all?parent_id=123
     * Every authorized pickup a parent has ever added.
     * Callable by the parent themselves, or by a principal/teacher/school-admin
     * viewing a parent belonging to their own institution.
     */
    public function all(Request $request)
    {
        $request->validate([
            'parent_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        try {
            $actor = auth()->user();
            $parentId = (int) $request->input('parent_id');

            if ($actor->role === UserRole::Parent) {
                if ($parentId !== $actor->id) {
                    throw new AuthorizationException('You can only view your own authorized pickups.');
                }

                $parent = $actor;
            } else {
                $parent = User::where('id', $parentId)
                    ->where('role', UserRole::Parent->value)
                    ->first();

                if (!$parent || $parent->institution_id !== $actor->institution_id) {
                    throw new AuthorizationException("You are not authorized to view this parent's authorized pickups.");
                }
            }

            $authorizedPickups = AuthorizedPickup::where('parent_id', $parentId)
                ->orderByDesc('updated_at')
                ->get()
                ->each(fn (AuthorizedPickup $pickup) => $pickup->setRelation('parent', $parent));

            return $this->successResponse(
                AuthorizedPickupResource::collection($authorizedPickups),
                'Authorized pickups retrieved successfully'
            );
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'sur_name' => ['nullable', 'string', 'max:255'],
            'relationship' => ['nullable', 'string', 'max:255'],
            'phone_number' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:1000'],
        ]);

        try {
            $parent = auth()->user();

            $authorizedPickup = $parent->authorizedPickups()->create($data);

            // First one ever added automatically becomes the current pickup.
            if (!$parent->current_authorized_pickup_id) {
                $parent->update(['current_authorized_pickup_id' => $authorizedPickup->id]);
            }

            $this->notifyAuthorizedPickupChange($authorizedPickup, $parent, 'created');

            return $this->successResponse(
                new AuthorizedPickupResource($authorizedPickup),
                'Authorized pickup created successfully',
                201
            );
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    /**
     * PATCH /parent/authorized-pickup/{authorizedPickup}/set-current
     * Select which of the parent's authorized pickups is the current one.
     */
    public function setCurrent(AuthorizedPickup $authorizedPickup)
    {
        try {
            if ($authorizedPickup->parent_id !== auth()->id()) {
                throw new AuthorizationException('You are not authorized to manage this authorized pickup.');
            }

            auth()->user()->update(['current_authorized_pickup_id' => $authorizedPickup->id]);

            $this->notifyAuthorizedPickupChange($authorizedPickup, auth()->user(), 'set_current');

            return $this->successResponse(
                new AuthorizedPickupResource($authorizedPickup),
                'Current authorized pickup updated successfully'
            );
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function update(Request $request, AuthorizedPickup $authorizedPickup)
    {
        $data = $request->validate([
            'first_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'last_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'sur_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'relationship' => ['sometimes', 'nullable', 'string', 'max:255'],
            'phone_number' => ['sometimes', 'string', 'max:255'],
            'address' => ['sometimes', 'string', 'max:1000'],
        ]);

        try {
            if ($authorizedPickup->parent_id !== auth()->id()) {
                throw new AuthorizationException('You are not authorized to manage this authorized pickup.');
            }

            $authorizedPickup->update($data);

            $this->notifyAuthorizedPickupChange($authorizedPickup, auth()->user(), 'updated');

            return $this->successResponse(
                new AuthorizedPickupResource($authorizedPickup->fresh()),
                'Authorized pickup updated successfully'
            );
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function destroy(AuthorizedPickup $authorizedPickup)
    {
        try {
            if ($authorizedPickup->parent_id !== auth()->id()) {
                throw new AuthorizationException('You are not authorized to manage this authorized pickup.');
            }

            $this->notifyAuthorizedPickupChange($authorizedPickup, auth()->user(), 'deleted');

            $authorizedPickup->delete();

            return $this->successResponse(null, 'Authorized pickup deleted successfully');
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    /**
     * Notify every teacher of the parent's children, plus the principal of
     * each child's institution, whenever an authorized pickup is created,
     * updated, deleted, or switched as the current one. FCM push + a
     * NotificationLog row only — no broadcasting events are fired.
     */
    private function notifyAuthorizedPickupChange(AuthorizedPickup $authorizedPickup, User $parent, string $action): void
    {
        $students = Student::where('guardian_id', $parent->id)
            ->whereNotNull('classroom_id')
            ->with('classroom.teachers')
            ->get();

        if ($students->isEmpty()) {
            return;
        }

        $parentName = trim("{$parent->first_name} {$parent->last_name} {$parent->sur_name}");
        $pickupName = $authorizedPickup->full_name ?: 'an authorized pickup contact';

        $actionText = match ($action) {
            'created' => 'added a new authorized pickup contact',
            'updated' => 'updated an authorized pickup contact',
            'deleted' => 'removed an authorized pickup contact',
            'set_current' => 'changed the current authorized pickup contact',
            default => 'updated authorized pickup details',
        };

        $title = 'Authorized Pickup Updated';
        $message = "{$parentName} {$actionText}: {$pickupName}.";

        $recipients = collect();

        foreach ($students as $student) {
            foreach ($student->classroom?->teachers ?? [] as $teacher) {
                $recipients->put($teacher->id, $teacher);
            }

            $principal = User::where('institution_id', $student->institution_id)
                ->where('role', UserRole::Principal)
                ->first();

            if ($principal) {
                $recipients->put($principal->id, $principal);
            }
        }

        foreach ($recipients as $recipient) {
            $this->sendAuthorizedPickupNotification($recipient, $parent, $authorizedPickup, $title, $message, $action);
        }
    }

    private function sendAuthorizedPickupNotification(
        User $recipient,
        User $parent,
        AuthorizedPickup $authorizedPickup,
        string $title,
        string $message,
        string $action
    ): void {
        $type = 'authorized_pickup_' . $action;

        $notificationLog = NotificationLog::create([
            'user_id' => $recipient->id,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'is_read' => false,
            'meta' => [
                'recipient_role' => $recipient->role?->value,
                'parent_id' => (string) $parent->id,
                'parent_name' => trim("{$parent->first_name} {$parent->last_name} {$parent->sur_name}"),
                'authorized_pickup_id' => (string) $authorizedPickup->id,
                'authorized_pickup_name' => $authorizedPickup->full_name,
                'action' => $action,
            ],
            'sent_at' => now($recipient->timezone ?? 'UTC'),
        ]);

        if ($recipient->notifications_enabled && $recipient->fcm_token) {
            app(FirebaseNotificationService::class)->sendToToken(
                $recipient->fcm_token,
                $title,
                $message,
                [
                    'type' => $type,
                    'authorized_pickup_id' => (string) $authorizedPickup->id,
                    'parent_id' => (string) $parent->id,
                ]
            );
        }
    }
}
