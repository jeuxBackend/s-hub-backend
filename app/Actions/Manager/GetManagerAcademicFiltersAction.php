<?php

namespace App\Actions\Manager;

use App\Models\Classroom;
use App\Models\Institution;
use App\Models\Subject;

class GetManagerAcademicFiltersAction
{
    /**
     * Schools, classes and subjects across every school this manager owns —
     * populates the dropdowns on the Academic Performance page.
     */
    public function handle(array $institutionIds): array
    {
        $schools = Institution::whereIn('id', $institutionIds)
            ->orderBy('name')
            ->get(['id', 'name', 'region', 'logo']);

        $classes = Classroom::whereIn('institution_id', $institutionIds)
            ->orderBy('name')
            ->get(['id', 'name', 'institution_id']);

        $subjects = Subject::whereIn('institution_id', $institutionIds)
            ->orderBy('name')
            ->get(['id', 'name', 'institution_id', 'classroom_id']);

        $regions = Institution::whereIn('id', $institutionIds)
            ->whereNotNull('region')
            ->where('region', '!=', '')
            ->distinct()
            ->orderBy('region')
            ->pluck('region')
            ->values();

        return [
            'schools' => $schools,
            'classes' => $classes,
            'subjects' => $subjects,
            'regions' => $regions,
        ];
    }
}
