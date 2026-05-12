<?php

namespace App\Actions\Admin;

use App\Enums\AdminRole;
use App\Models\Admin;
use App\Models\Institution;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class CreateSubAdminAction
{
    public function handle(array $data)
    {
        $data['password'] = Hash::make($data['password']);
        $data['role'] = AdminRole::SubAdmin;

        if (isset($data['profile_image']) && $data['profile_image'] instanceof \Illuminate\Http\UploadedFile) {
            $data['profile_image'] = $data['profile_image']->store('admin_profiles', 'public');
        }

        return Admin::create($data);
    }
}
