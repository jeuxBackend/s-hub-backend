<?php

namespace App\Actions\Admin;

use App\Enums\AdminRole;
use App\Models\Admin;
use Illuminate\Support\Facades\Storage;

class DeleteSubAdminAction
{
    public function handle($id)
    {
        $subAdmin = Admin::where('role', AdminRole::SubAdmin)->findOrFail($id);

        if ($subAdmin->profile_image) {
            Storage::disk('public')->delete($subAdmin->profile_image);
        }

        // Their sub-sub-admins are removed with them (database cascade);
        // clean up the profile pictures first.
        $subAdmin->subSubAdmins()->get()->each(function ($child) {
            if ($child->profile_image) {
                Storage::disk('public')->delete($child->profile_image);
            }
            $child->tokens()->delete();
        });

        $subAdmin->delete();
        return true;
    }
}
