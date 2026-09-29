<?php

namespace App\Actions\Dashboard;

use App\Models\Institution;

class GetDistinctRegionsAction
{
    /**
     * Every distinct, non-empty region value in use across all institutions.
     */
    public function handle(): array
    {
        return Institution::whereNotNull('region')
            ->where('region', '!=', '')
            ->distinct()
            ->orderBy('region')
            ->pluck('region')
            ->values()
            ->all();
    }
}
