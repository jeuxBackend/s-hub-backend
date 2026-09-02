<?php

namespace App\Actions\Admin;

use App\Enums\UserRole;
use App\Models\User;

class DeleteGlobalTeacherAction
{
    public function handle($id)
    {
        $teacher = User::whereIn('role', [UserRole::Teacher->value, UserRole::SchoolAdmin->value])->findOrFail($id);
        $teacher->delete();
        return true;
    }
}
