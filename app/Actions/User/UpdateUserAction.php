<?php

namespace App\Actions\User;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\UploadedFile;

class UpdateUserAction
{
    public function handle(int $id, array $data, User $requester): User
    {
        $user = User::findOrFail($id);

        unset($data['role'], $data['created_by'], $data['otp_verified']);

        // ✅ Handle profile picture
        if (!empty($data['profile_picture']) && $data['profile_picture'] instanceof UploadedFile) {
            $data['profile_picture'] = $data['profile_picture']->store('profile_pictures', 'public');
        }
        // dd($data['profile_picture']);
        // ✅ Secure password update
        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        // ✅ School admin permissions only if applicable
        // if ($user->role !== 'school_admin') {
        //     unset($data['permissions']);
        // }
        // ✅ Ensure institution_id is set correctly
        // $updatedData=$data['first_name'];
        // dd($updatedData);
        // exit;
        $user->fill($data);
        $user->save();

        return $user->refresh();
    }
}
