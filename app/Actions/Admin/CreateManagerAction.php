<?php

namespace App\Actions\Admin;

use App\Enums\AdminRole;
use App\Models\Admin;
use Illuminate\Support\Facades\Hash;

class CreateManagerAction
{
    public function handle(array $data)
    {
        $data['password'] = Hash::make($data['password']);
        $data['role'] = AdminRole::Manager;
        return Admin::create($data);
    }
}
