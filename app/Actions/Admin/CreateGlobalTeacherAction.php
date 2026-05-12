<?php

namespace App\Actions\Admin;

use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Support\Facades\Hash;

class CreateGlobalTeacherAction
{
    public function handle(array $data)
    {
        $data['password'] = Hash::make($data['password']);
        $data['role'] = UserRole::Teacher->value;
        return User::create($data);
    }
}
