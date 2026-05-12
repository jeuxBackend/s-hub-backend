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

        $subAdmin->delete();
        return true;
    }
}
