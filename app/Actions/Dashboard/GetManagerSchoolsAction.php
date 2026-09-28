<?php

namespace App\Actions\Dashboard;

use App\Models\Institution;

class GetManagerSchoolsAction
{
    public function handle($managerId, ?array $institutionIds = null)
    {
        $query = Institution::where('manager_id', $managerId);

        if ($institutionIds !== null) {
            $query->whereIn('id', $institutionIds);
        }

        return $query->get();
    }
}