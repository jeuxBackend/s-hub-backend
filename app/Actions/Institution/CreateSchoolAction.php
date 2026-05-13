<?php

namespace App\Actions\Institution;

use App\Models\Institution;

class CreateSchoolAction
{
    public function handle(array $data)
    {
        return Institution::create($data);
    }
}
