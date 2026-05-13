<?php

namespace App\Actions\Institution;

use App\Models\Institution;

class UpdateSchoolAction
{
    public function handle(array $data, $id)
    {
        $school = Institution::findOrFail($id);
        $school->update($data);
        return $school;
    }
}
