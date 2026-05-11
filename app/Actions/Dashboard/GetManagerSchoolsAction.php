<?php

namespace App\Actions\Dashboard;

use App\Models\Institution;

class GetManagerSchoolsAction
{
    public function handle($managerId)
    {
        return Institution::where('manager_id', $managerId)->get();
    }
}