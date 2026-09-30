<?php

namespace App\Actions\User;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Hash;

class ChangePasswordAction
{
    public function handle(array $data, Authenticatable $user): void
    {
        // ✅ Check current password
        if (!Hash::check($data['current_password'], $user->password)) {
            abort(403, 'Current password is incorrect.');
        }

        // ✅ Update password securely
        $updateData = ['password' => Hash::make($data['password'])];

        if ($user instanceof \App\Models\Admin) {
            $updateData['password_changed_at'] = now();
        }

        $user->update($updateData);
    }
}
