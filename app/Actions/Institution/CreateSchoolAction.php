<?php

namespace App\Actions\Institution;

use App\Models\Institution;
use Illuminate\Support\Facades\Storage;

class CreateSchoolAction
{
    public function handle(array $data)
    {
        if (isset($data['school_logo']) && $data['school_logo'] instanceof \Illuminate\Http\UploadedFile) {
            $data['logo'] = $data['school_logo']->store('institutions/logos', 'public');
        }

        return Institution::create($data)->fresh();
    }
}
