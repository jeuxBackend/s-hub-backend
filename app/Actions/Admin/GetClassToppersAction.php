<?php

namespace App\Actions\Admin;

use App\Enums\AdminRole;
use App\Models\Admin;
use App\Models\Institution;
use App\Models\Student;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GetClassToppersAction
{
    /**
     * Default number of distinct rank positions to return per classroom.
     * All students tied at a qualifying rank are included.
     */
    public const DEFAULT_TOP_RANKS = 3;

    /**
     * Return toppers (with tie support) per classroom, grouped by school.
     *
     * @param  Admin  $admin    The authenticated admin or sub-admin
     * @param  array  $filters  Optional: [
     *                              'school_id' => int,
     *                              'classroom_id' => int,
     *                              'term' => string,
     *                              'limit' => int (number of ranks: 1, 3, etc.)
     *                          ]
     */
    public function handle(Admin $admin, array $filters = []): Collection
    {
        $institutionIds = $this->resolveInstitutionIds($admin, $filters);

        // institutionIds === null  → no restriction (admin sees all)
        // institutionIds === []    → sub-admin with no matching country schools → empty
        if ($institutionIds !== null && count($institutionIds) === 0) {
            return collect();
        }

        $topRanks = isset($filters['limit']) ? max(1, (int) $filters['limit']) : self::DEFAULT_TOP_RANKS;
        $schoolId = $filters['school_id'] ?? $filters['institution_id'] ?? null;

        // ------------------------------------------------------------------
        // 1. Compute performance percentage per student via aggregation
        // ------------------------------------------------------------------
        $perfQuery = DB::table('student_grades as sg')
            ->join('students as s', 's.id', '=', 'sg.student_id')
            ->where('sg.type', 'years_marks')
            ->whereNotNull('sg.score')
            ->whereNotNull('sg.total')
            ->where('sg.total', '>', 0)
            ->groupBy('sg.student_id', 's.classroom_id', 's.institution_id')
            ->select([
                'sg.student_id',
                's.classroom_id',
                's.institution_id',
                DB::raw('ROUND(SUM(sg.score), 2) AS obtained_marks'),
                DB::raw('ROUND(SUM(sg.total), 2) AS total_marks'),
                DB::raw('ROUND(SUM(sg.score) / SUM(sg.total) * 100, 2) AS performance_pct'),
            ]);

        if ($institutionIds !== null) {
            $perfQuery->whereIn('s.institution_id', $institutionIds);
        }

        if (!empty($schoolId)) {
            $perfQuery->where('s.institution_id', $schoolId);
        }

        if (!empty($filters['classroom_id'])) {
            $perfQuery->where('s.classroom_id', $filters['classroom_id']);
        }

        if (!empty($filters['term'])) {
            $perfQuery->where('sg.term', $filters['term']);
        }

        $performances = $perfQuery->get();

        if ($performances->isEmpty()) {
            return collect();
        }

        // ------------------------------------------------------------------
        // 2. Per classroom: find top-N distinct percentages, keep tied students
        // ------------------------------------------------------------------
        $studentIds = $this->resolveTopperIds($performances, $topRanks);

        if (empty($studentIds)) {
            return collect();
        }

        // ------------------------------------------------------------------
        // 3. Load student records with minimal relations
        // ------------------------------------------------------------------
        $students = Student::whereIn('id', $studentIds)
            ->with(['classroom:id,name,code,institution_id', 'classroom.institution:id,name'])
            ->get([
                'id',
                'first_name',
                'last_name',
                'sur_name',
                'profile_picture',
                'registration_number',
                'gender',
                'classroom_id',
                'institution_id',
            ]);

        // Attach performance data to each student model
        $perfMap = $performances->keyBy('student_id');
        foreach ($students as $student) {
            $p = $perfMap->get($student->id);
            $student->performance_pct = $p ? (float) $p->performance_pct : 0.0;
            $student->obtained_marks  = $p ? (float) $p->obtained_marks : 0.0;
            $student->total_marks     = $p ? (float) $p->total_marks : 0.0;
        }

        // ------------------------------------------------------------------
        // 4. Load schools (scoped)
        // ------------------------------------------------------------------
        $schoolIds = $students->pluck('institution_id')->unique()->values()->all();
        $schools   = Institution::whereIn('id', $schoolIds)
            ->get(['id', 'name', 'country', 'region']);

        // ------------------------------------------------------------------
        // 5. Group: school → classroom → ranked toppers
        // ------------------------------------------------------------------
        return $schools->map(function ($school) use ($students) {
            $schoolStudents = $students->where('institution_id', $school->id);

            $classrooms = $schoolStudents
                ->groupBy('classroom_id')
                ->map(function ($classStudents) {
                    $classroom = $classStudents->first()->classroom;

                    // Sort desc by performance, assign dense rank
                    $sorted = $classStudents->sortByDesc('performance_pct')->values();
                    $ranked = $this->assignRanks($sorted);

                    return [
                        'classroom' => [
                            'id'   => $classroom?->id,
                            'name' => $classroom?->name,
                            'code' => $classroom?->code,
                        ],
                        'toppers' => $ranked,
                    ];
                })
                ->values();

            return [
                'school' => [
                    'id'      => $school->id,
                    'name'    => $school->name,
                    'country' => $school->country,
                    'region'  => $school->region,
                ],
                'classrooms' => $classrooms,
            ];
        });
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Resolve institution IDs for the given admin.
     * Returns null (unrestricted) or an array of IDs.
     */
    private function resolveInstitutionIds(Admin $admin, array $filters): ?array
    {
        // Pure admin: no restriction
        if ($admin->role === AdminRole::Admin) {
            return null;
        }

        // Sub-admin: strictly scoped to their country schools
        if ($admin->country) {
            $countrySchools = Institution::where('country', $admin->country);
            $assigned = $admin->assignedSchools()->pluck('id')->all();
            if (!empty($assigned)) {
                $countrySchools->whereIn('id', $assigned);
            }
            return $countrySchools->pluck('id')->all();
        }

        return $admin->assignedInstitutionIds();
    }

    /**
     * For each classroom, pick the student IDs whose performance falls within
     * the top-N distinct rank positions (ties included).
     *
     * @param  Collection  $performances  Collection of stdClass {student_id, classroom_id, performance_pct}
     * @param  int         $topRanks
     * @return array<int>
     */
    private function resolveTopperIds(Collection $performances, int $topRanks): array
    {
        $topperIds = [];

        $byClassroom = $performances->groupBy('classroom_id');

        foreach ($byClassroom as $classroomId => $rows) {
            // Get distinct sorted percentages descending
            $distinctPcts = $rows
                ->pluck('performance_pct')
                ->unique()
                ->sortDesc()
                ->values()
                ->take($topRanks)
                ->all();

            if (empty($distinctPcts)) {
                continue;
            }

            $minPct = end($distinctPcts); // lowest qualifying percentage

            // All students at or above the minimum qualifying percentage
            $qualifying = $rows->filter(
                fn($r) => (float) $r->performance_pct >= (float) $minPct
            )->pluck('student_id')->all();

            $topperIds = array_merge($topperIds, $qualifying);
        }

        return array_unique($topperIds);
    }

    /**
     * Assign dense rank to an already-sorted (desc) collection of students.
     * Students with the same performance_pct share the same rank.
     */
    private function assignRanks(Collection $students): array
    {
        $result  = [];
        $rank    = 0;
        $prevPct = null;
        $counter = 0;

        foreach ($students as $student) {
            $counter++;

            if ($student->performance_pct !== $prevPct) {
                $rank    = $counter;
                $prevPct = $student->performance_pct;
            }

            $result[] = [
                'rank'                   => $rank,
                'performance_percentage' => $student->performance_pct,
                'obtained_marks'         => $student->obtained_marks,
                'total_marks'            => $student->total_marks,
                'student'                => [
                    'id'                  => $student->id,
                    'first_name'          => $student->first_name,
                    'last_name'           => $student->last_name,
                    'sur_name'            => $student->sur_name,
                    'profile_picture'     => $student->profile_picture,
                    'registration_number' => $student->registration_number,
                    'gender'              => $student->gender?->value,
                ],
            ];
        }

        return $result;
    }
}
