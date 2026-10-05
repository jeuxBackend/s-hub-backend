<?php

namespace App\Actions\SchoolAsset;

use App\Models\SchoolAsset;
use Illuminate\Support\Facades\Storage;

class DeleteSchoolAssetAction
{
    public function handle(SchoolAsset $asset): void
    {
        if ($asset->file_path) {
            Storage::disk('public')->delete($asset->file_path);
        }

        $asset->delete();
    }
}
