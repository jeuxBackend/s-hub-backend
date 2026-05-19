<?php

namespace App\Actions\User;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

class UpdateUserAction
{
    public function handle(int $id, $data, User $requester): User
    {
        $user = User::findOrFail($id);

        unset($data['role'], $data['created_by'], $data['otp_verified']);
        if (!empty($data['profile_picture']) && $data['profile_picture'] instanceof UploadedFile) {
            $data['profile_picture'] = $data['profile_picture']->store('profile_pictures', 'public');
        }
        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }


        Log::info($data);

        $data['guardian_name'] = $data['first_name'] ?? $user->guardian_name;

        $user->fill($data);

        $user->save();

        return $user->refresh();
    }
}
