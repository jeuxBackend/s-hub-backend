<?php

namespace App\Actions\Admin;

use App\Models\User;

class DeleteGlobalTeacherAction
{
    public function handle($id)
    {
        $teacher = User::where('role', \App\Enums\UserRole::Teacher->value)->findOrFail($id);
        $teacher->delete();
        return true;
    }
}
