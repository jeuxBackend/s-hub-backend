<?php

namespace App\Actions\Dashboard;

use App\Models\Institution;

class GetDistinctRegionsAction
{
    /**
     * Every distinct, non-empty region value in use across all institutions,
     * or, when $institutionIds is given, only within those institutions.
     */
    public function handle(?array $institutionIds = null): array
    {
        return Institution::whereNotNull('region')
            ->where('region', '!=', '')
            ->when($institutionIds !== null, fn ($q) => $q->whereIn('id', $institutionIds))
            ->distinct()
            ->orderBy('region')
            ->pluck('region')
            ->values()
            ->all();
    }
}
