<?php

namespace App\Actions\Admin;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UpdateGlobalTeacherAction
{
    public function handle(array $data, $id)
    {
        $teacher = User::where('role', \App\Enums\UserRole::Teacher->value)->findOrFail($id);
        if (isset($data['password']) && !empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }
        $teacher->update($data);
        return $teacher;
    }
}
