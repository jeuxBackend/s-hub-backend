<?php

namespace App\Actions\SchoolAsset;

use App\Models\SchoolAsset;
use Illuminate\Support\Facades\Storage;

class UpdateSchoolAssetAction
{
    public function handle(SchoolAsset $asset, array $data): SchoolAsset
    {
        $newFormat = $data['asset_format'] ?? $asset->asset_format->value;

        if ($newFormat === 'image' && !empty($data['file']) && $data['file'] instanceof \Illuminate\Http\UploadedFile) {
            $this->deleteExistingFile($asset);
            $data['file_path'] = $data['file']->store('school_assets', 'public');
            $data['text_value'] = null;
        } elseif ($newFormat === 'text') {
            $this->deleteExistingFile($asset);
            $data['file_path'] = null;
        }

        unset($data['file']);

        $asset->update($data);

        return $asset->fresh();
    }

    private function deleteExistingFile(SchoolAsset $asset): void
    {
        if ($asset->file_path) {
            Storage::disk('public')->delete($asset->file_path);
        }
    }
}
