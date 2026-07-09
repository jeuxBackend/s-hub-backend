<?php

namespace App\Actions\Institute;

use App\Models\Institution;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class UpdateInstitueAction
{
    public function handle(Institution $institution, array $data)
    {
        Log::info('Updating institution payload', [
            'institution_id' => $institution->id,
            'incoming_keys' => array_keys($data),
            'has_logo_upload' => isset($data['logo']) && $data['logo'] instanceof \Illuminate\Http\UploadedFile,
            'current_logo_raw' => $institution->getRawOriginal('logo'),
        ]);

        if (array_key_exists('logo', $data) && !($data['logo'] instanceof \Illuminate\Http\UploadedFile)) {
            unset($data['logo']);
        }

        if (isset($data['logo']) && $data['logo'] instanceof \Illuminate\Http\UploadedFile) {
            $existingLogoPath = $institution->getRawOriginal('logo');

            if ($existingLogoPath && Storage::disk('public')->exists($existingLogoPath)) {
                Storage::disk('public')->delete($existingLogoPath);
            }

            $data['logo'] = $data['logo']->store('logos', 'public');

            Log::info('Institution logo stored', [
                'institution_id' => $institution->id,
                'stored_logo_path' => $data['logo'],
                'old_logo_deleted' => !empty($existingLogoPath),
            ]);
        }

        $fillable = $institution->getFillable();
        $filtered = collect($data)->only($fillable)->toArray();

        Log::info('Institution update filtered payload', [
            'institution_id' => $institution->id,
            'filtered_keys' => array_keys($filtered),
            'logo_value' => $filtered['logo'] ?? null,
        ]);

        $institution->update($filtered);

        $freshInstitution = $institution->fresh();

        Log::info('Institution update completed', [
            'institution_id' => $institution->id,
            'saved_logo_raw' => $freshInstitution?->getRawOriginal('logo'),
        ]);

        return $freshInstitution;
    }
}
