<?php

namespace App\Actions\Auth;

use App\Http\Resources\FamilyMemberResource;
use App\Models\User;
use App\Models\FamilyMember;
use App\Models\SchoolAlert;
use App\Enums\UserRole;
use Illuminate\Support\Facades\Hash;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use App\Http\Resources\UserResource;

class LoginAction
{
    public function handle(array $data): array
    {
        $loginField = filter_var($data['login'], FILTER_VALIDATE_EMAIL)
            ? 'email'
            : 'phone_number';

        [$user, $familyMember, $isRegistered] = $this->resolveLoginTarget($data, $loginField);

        if (!$user->status) {
            throw new AuthorizationException('Your account has been blocked.');
        }

        $this->validateRoleSpecific($user, $data);

        if (!$user->otp_verified && $user->role !== UserRole::Parent) {
            throw new AuthorizationException('Please verify your account with OTP first.');
        }
        $user->update([
            'device_id' => $data['device_id'] ?? $user->device_id,
            'fcm_token' => $data['fcm_token'] ?? $user->fcm_token,
            'timezone' => $data['timezone'] ?? $user->timezone,
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;
        $this->loadMinimalRelations($user);
        $this->attachInstitutionAlertCount($user);

        // Get unread notification count
        $unreadNotificationCount = \App\Models\NotificationLog::where('user_id', $user->id)
            ->where('is_read', false)
            ->count();

        return [
            'user' => new UserResource($user),
            'token' => $token,
            'role' => $user->role->value,
            'is_registered' => $isRegistered,
            'unread_notification_count' => $unreadNotificationCount,
            'logged_in_via_family_member' => (bool) $familyMember,
            'family_member' => $familyMember ? new FamilyMemberResource($familyMember) : null,
        ];
    }

    private function resolveLoginTarget(array $data, string $loginField): array
    {
        $user = User::where($loginField, $data['login'])->first();

        if ($user) {
            $isRegistered = !is_null($user->password);

            if ($isRegistered) {
                if (!isset($data['password']) || !Hash::check($data['password'], $user->password)) {
                    throw ValidationException::withMessages([
                        'password' => 'Incorrect password.',
                    ]);
                }
            } elseif ($loginField !== 'email') {
                throw ValidationException::withMessages([
                    'login' => 'Please login using your registered email.',
                ]);
            }

            return [$user, null, $isRegistered];
        }

        $familyMember = FamilyMember::with('parent')->where($loginField, $data['login'])->first();

        if (!$familyMember || !isset($data['password']) || !Hash::check($data['password'], $familyMember->password)) {
            throw ValidationException::withMessages([
                'login' => 'Invalid credentials.',
            ]);
        }

        if (!$familyMember->parent) {
            throw ValidationException::withMessages([
                'login' => 'This family member is not linked to an active parent account.',
            ]);
        }

        return [$familyMember->parent, $familyMember, true];
    }

    private function validateRoleSpecific(User $user, array $data): void
    {
        if ($user->role === UserRole::Parent) {
            // You can add institution check here if needed later
        }

        if ($user->role === UserRole::SchoolAdmin) {
            if (!$user->creator?->institution) {
                throw new AuthorizationException('School admin is not assigned to any institution.');
            }
        }
    }

    private function loadMinimalRelations(User $user): void
    {
        $relations = [];

        if (in_array($user->role, [UserRole::Principal, UserRole::Teacher, UserRole::SchoolAdmin, UserRole::Parent], true)) {
            $relations[] = 'institution';
        }

        if ($user->role === UserRole::Parent) {
            $relations[] = 'guardianStudents';
            $relations[] = 'familyMembers';
        }

        $user->load($relations);
    }

    private function attachInstitutionAlertCount(User $user): void
    {
        if (!in_array($user->role, [UserRole::Principal, UserRole::SchoolAdmin, UserRole::Teacher, UserRole::Parent], true)) {
            return;
        }

        if (!$user->relationLoaded('institution') || !$user->institution) {
            return;
        }

        $activeAlertsQuery = SchoolAlert::where('institution_id', $user->institution_id);

        if ($user->role === UserRole::Principal) {
            $activeAlertsQuery->where('status', '!=', 'resolved');
        } else {
            $activeAlertsQuery
                ->where('status', 'active')
                ->withinActiveCountWindow();
        }

        $user->institution->setAttribute(
            'active_alerts_count',
            $activeAlertsQuery->count()
        );

        if (in_array($user->role, [UserRole::Teacher, UserRole::SchoolAdmin], true)) {
            $user->institution->setAttribute(
                'potential_abduction_alerts_count',
                SchoolAlert::where('institution_id', $user->institution_id)
                    ->where('type', 'abduction')
                    ->where('status', 'potential')
                    ->count()
            );
        }
    }
}
