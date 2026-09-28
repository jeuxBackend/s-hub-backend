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

        $schoolIdsProvided = array_key_exists('school_ids', $data);
        $schoolIds = $data['school_ids'] ?? [];
        unset($data['school_ids']);

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

        if ($schoolIdsProvided) {
            // Full sync: unassign anything no longer in the list, assign the rest.
            Institution::where('subadmin_id', $subAdmin->id)
                ->whereNotIn('id', $schoolIds)
                ->update(['subadmin_id' => null]);

            if (!empty($schoolIds)) {
                Institution::whereIn('id', $schoolIds)->update(['subadmin_id' => $subAdmin->id]);
            }
        }

        return $subAdmin;
    }
}
