<?php

namespace App\Actions\Institution;

use App\Models\Institution;
use Illuminate\Support\Facades\Storage;

class UpdateSchoolAction
{
    public function handle(array $data, $id)
    {
        $school = Institution::findOrFail($id);

        if (isset($data['school_logo']) && $data['school_logo'] instanceof \Illuminate\Http\UploadedFile) {
            // Delete old logo if exists — must use the raw stored path, since
            // the `logo` accessor returns a full asset() URL, not the path.
            $oldLogoPath = $school->getRawOriginal('logo');
            if ($oldLogoPath) {
                Storage::disk('public')->delete($oldLogoPath);
            }
            $data['logo'] = $data['school_logo']->store('institutions/logos', 'public');
        }

        $school->update($data);
        return $school->fresh(['category']);
    }
}
