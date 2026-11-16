<?php

namespace App\Actions\SchoolAsset;

use App\Models\Institution;
use App\Models\SchoolAsset;
use Illuminate\Http\UploadedFile;

class SaveInstitutionWatermarkAction
{
    public function __construct(
        private StoreSchoolAssetAction $store,
        private UpdateSchoolAssetAction $update,
        private DeleteSchoolAssetAction $delete,
    ) {
    }

    /**
     * Sets, replaces or removes the school's watermark (one per school, text or image).
     */
    public function handle(Institution $institution, int $userId, ?UploadedFile $image, ?string $text, bool $remove = false): void
    {
        $existing = SchoolAsset::where('institution_id', $institution->id)
            ->where('type', 'watermark')
            ->first();

        if ($remove) {
            if ($existing) {
                $this->delete->handle($existing);
            }

            return;
        }

        if (!$image && ($text === null || trim($text) === '')) {
            return;
        }

        $data = $image
            ? ['asset_format' => 'image', 'file' => $image, 'text_value' => null]
            : ['asset_format' => 'text', 'text_value' => trim($text)];

        if ($existing) {
            $this->update->handle($existing, $data);

            return;
        }

        $this->store->handle($data + [
            'institution_id' => $institution->id,
            'type' => 'watermark',
            'created_by' => $userId,
        ]);
    }
}
