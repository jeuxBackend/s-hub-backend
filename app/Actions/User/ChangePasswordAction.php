<?php

namespace App\Actions\User;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class ChangePasswordAction
{
    public function handle(array $data, User $user): void
    {
        // ✅ Check current password
        if (! Hash::check($data['current_password'], $user->password)) {
            abort(403, 'Current password is incorrect.');
        }

        // ✅ Update password securely
        $user->update([
            'password' => Hash::make($data['password']),
        ]);
    }
}
