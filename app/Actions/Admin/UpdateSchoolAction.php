<?php

namespace App\Actions\Admin;

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
