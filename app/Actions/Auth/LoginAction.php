<?php

namespace App\Actions\Auth;

use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Support\Facades\Hash;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use App\Http\Resources\UserResource;
use App\Http\Resources\InstitutionResource;
use App\Http\Resources\StudentResource;

class LoginAction
{
    public function handle(array $data): array
    {
        $loginField = filter_var($data['login'], FILTER_VALIDATE_EMAIL)
            ? 'email'
            : 'phone_number';

        $user = User::where($loginField, $data['login'])->first();

        if (!$user) {
            throw ValidationException::withMessages([
                'login' => 'Invalid credentials.',
            ]);
        }

        if (!Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'password' => 'Incorrect password.',
            ]);
        }

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

        // Get unread notification count
        $unreadNotificationCount = \App\Models\NotificationLog::where('user_id', $user->id)
            ->where('is_read', false)
            ->count();

        return [
            'user' => new UserResource($user),
            'token' => $token,
            'role' => $user->role->value,
            'unread_notification_count' => $unreadNotificationCount,
        ];
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

        if ($user->role === UserRole::Principal) {
            $relations[] = 'institution';
        }

        if ($user->role === UserRole::SchoolAdmin) {
            $relations[] = 'creator.institution';
        }

        if ($user->role === UserRole::Parent) {
            $relations[] = 'guardianStudents';
        }

        $user->load($relations);
    }
}
