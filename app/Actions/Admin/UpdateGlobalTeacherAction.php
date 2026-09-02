<?php

namespace App\Actions\Admin;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UpdateGlobalTeacherAction
{
    public function handle(array $data, $id)
    {
        $teacher = User::whereIn('role', [UserRole::Teacher->value, UserRole::SchoolAdmin->value])->findOrFail($id);
        if (isset($data['password']) && !empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }
        $teacher->update($data);
        return $teacher;
    }
}
