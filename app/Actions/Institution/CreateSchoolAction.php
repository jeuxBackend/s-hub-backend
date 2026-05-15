<?php

namespace App\Actions\Institution;

use App\Models\Institution;
use Illuminate\Support\Facades\Storage;

class CreateSchoolAction
{
    public function handle(array $data)
    {
        if (isset($data['logo']) && $data['logo'] instanceof \Illuminate\Http\UploadedFile) {
            $data['logo'] = $data['logo']->store('institutions/logos', 'public');
        }

        return Institution::create($data);
    }
}
