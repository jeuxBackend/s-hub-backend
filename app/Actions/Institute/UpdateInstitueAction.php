<?php

namespace App\Actions\Institute;

use App\Models\Institution;
use Illuminate\Support\Facades\Storage;

class UpdateInstitueAction
{
    public function handle(Institution $institution, array $data)
    {
        // ✅ Handle optional logo upload
        if (isset($data['logo']) && $data['logo'] instanceof \Illuminate\Http\UploadedFile) {
            if ($institution->logo && Storage::exists($institution->logo)) {
                Storage::delete($institution->logo);
            }

            $data['logo'] = $data['logo']->store('logos', 'public');
        }

        // ✅ Filter only fillable fields to prevent mass-assignment issues
        $fillable = $institution->getFillable();
        $filtered = collect($data)->only($fillable)->toArray();

        // ✅ Update institution
        $institution->update($filtered);

        return $institution->fresh();
    }
}
