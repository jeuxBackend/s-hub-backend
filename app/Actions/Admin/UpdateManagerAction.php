<?php

namespace App\Actions\Admin;

use App\Models\Admin;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class UpdateManagerAction
{
    public function handle(array $data, $id)
    {
        $manager = Admin::findOrFail($id);
        
        if (isset($data['password']) && !empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }
        
        if (isset($data['profile_image']) && $data['profile_image'] instanceof \Illuminate\Http\UploadedFile) {
            if ($manager->profile_image) {
                Storage::disk('public')->delete($manager->profile_image);
            }

            $data['profile_image'] = $data['profile_image']->store('admin_profiles', 'public');
        }

        $previousStatus = $manager->status;
        $manager->update($data);

        if ($previousStatus !== $manager->status) {
            app(\App\Services\AdminNotificationService::class)->accountStatusChanged($manager, $manager->status !== 'active');
        }

        return $manager;
    }
}
