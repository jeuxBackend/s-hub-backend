<?php

namespace App\Actions\Admin;

use App\Models\Institution;

class CreateSchoolAction
{
    public function handle(array $data)
    {
        return Institution::create($data);
    }
}
