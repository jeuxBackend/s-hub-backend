<?php

namespace App\Actions\Admin;

use App\Enums\AdminRole;
use App\Models\Admin;
use App\Models\Institution;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class UpdateSubAdminAction
{
    public function handle(array $data, $id)
    {
        $subAdmin = Admin::where('role', AdminRole::SubAdmin)->findOrFail($id);

        if (isset($data['password']) && !empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        if (isset($data['profile_image']) && $data['profile_image'] instanceof \Illuminate\Http\UploadedFile) {
            if ($subAdmin->profile_image) {
                Storage::disk('public')->delete($subAdmin->profile_image);
            }
            $data['profile_image'] = $data['profile_image']->store('admin_profiles', 'public');
        }

        $subAdmin->update($data);
        return $subAdmin;
    }
}
