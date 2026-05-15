<?php

namespace App\Actions\Auth;

use App\Enums\AdminRole;
use App\Http\Resources\UserResource;
use App\Models\Admin;
use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Access\AuthorizationException;
use App\Http\Resources\AdminResource;   // Recommended

class AdminLoginAction
{
    public function handle(array $data): array
    {
        $loginField = filter_var($data['login'], FILTER_VALIDATE_EMAIL)
            ? 'email'
            : 'phone_number';

        $admin = Admin::where($loginField, $data['login'])->first();

        if ($admin) {
            return $this->loginAsAdmin($admin, $data);
        }

        $user = User::where($loginField, $data['login'])->first();

        if ($user) {
            return $this->loginAsUserStaff($user, $data);
        }

        throw ValidationException::withMessages([
            'login' => 'Invalid credentials.',
        ]);
    }

    private function loginAsAdmin(Admin $admin, array $data): array
    {
        if (!Hash::check($data['password'], $admin->password)) {
            throw ValidationException::withMessages([
                'password' => 'Incorrect password.',
            ]);
        }

        if (!$admin->status) {
            throw new AuthorizationException('Your account has been blocked.');
        }

        $this->updateFcmToken($admin, $data['fcm_token'] ?? null);
        $token = $admin->createToken('admin_auth_token')->plainTextToken;

        if ($admin->role === AdminRole::Manager) {
            $admin->load(['institutions']);
        }

        return [
            'user' => new AdminResource($admin),
            'token' => $token,
            'type' => 'admin'
        ];
    }

    private function loginAsUserStaff(User $user, array $data): array
    {
        if (!Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'password' => 'Incorrect password.',
            ]);
        }

        if (!$user->status) {
            throw new AuthorizationException('Your account has been blocked.');
        }

        if (!$this->isAllowedStaffRole($user->role)) {
            throw ValidationException::withMessages([
                'login' => 'You are not authorized to access the Admin Portal.',
            ]);
        }

        $this->validateRoleSpecific($user);
        $this->updateFcmToken($user, $data['fcm_token'] ?? null);

        $token = $user->createToken('admin_auth_token')->plainTextToken;

        $this->loadMinimalRelations($user);

        return [
            'user' => new UserResource($user),
            'token' => $token,
            'type' => 'staff'
        ];
    }

    private function isAllowedStaffRole(string $role): bool
    {
        return in_array($role, [
            UserRole::Principal->value,
            UserRole::SchoolAdmin->value,
        ]);
    }

    private function validateRoleSpecific(User $user): void
    {
        if ($user->role === UserRole::SchoolAdmin) {
            if (!$user->creator?->institution) {
                throw new AuthorizationException('School admin is not assigned to any institution.');
            }
        }
    }

    private function loadMinimalRelations(User $user): void
    {
        $relations = [];

        if (in_array($user->role, [UserRole::Principal, UserRole::SchoolAdmin])) {
            $relations[] = 'institution';
        }

        if ($user->role === UserRole::SchoolAdmin) {
            $relations[] = 'creator.institution';
        }

        if (!empty($relations)) {
            $user->load($relations);
        }
    }

    private function updateFcmToken($model, ?string $fcmToken): void
    {
        if ($fcmToken) {
            $model->update(['fcm_token' => $fcmToken]);
        }
    }
}