<?php

namespace App\Actions\Institution;

use App\Models\Institution;

class DeleteSchoolAction
{
    public function handle($id)
    {
        $school = Institution::findOrFail($id);
        $school->delete();
        return true;
    }
}
