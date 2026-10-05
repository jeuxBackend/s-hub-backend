<?php

namespace App\Actions\SchoolAsset;

use App\Models\SchoolAsset;

class StoreSchoolAssetAction
{
    public function handle(array $data): SchoolAsset
    {
        if (($data['asset_format'] ?? null) === 'image' && !empty($data['file']) && $data['file'] instanceof \Illuminate\Http\UploadedFile) {
            $data['file_path'] = $data['file']->store('school_assets', 'public');
        }

        unset($data['file']);

        return SchoolAsset::create($data);
    }
}
