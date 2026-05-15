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
            // Delete old logo if exists
            if ($school->logo) {
                Storage::disk('public')->delete($school->logo);
            }
            $data['logo'] = $data['school_logo']->store('institutions/logos', 'public');
        }

        $school->update($data);
        return $school;
    }
}
