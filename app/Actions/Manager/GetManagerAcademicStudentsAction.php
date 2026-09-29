<?php

namespace App\Actions\Manager;

use App\Models\Student;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Request as RequestFacade;

class GetManagerAcademicStudentsAction
{
    /**
     * Per-student, per-subject grade breakdown across this manager's
     * schools. Backs the "Student", "Toppers in school" (institution_id
     * filter + sort=performance_desc) and "Toppers in regions" (region
     * filter, no institution_id, + sort=performance_desc) tabs — the mock
     * frontend does not actually differentiate those two toppers views
     * beyond which filter is applied, so one endpoint covers all three.
     */
    public function handle(array $institutionIds, array $data = []): LengthAwarePaginator
    {
        $query = Student::query()
            ->whereIn('institution_id', $institutionIds)
            ->with([
                'institution:id,name,region',
                'classroom:id,name',
                'guardian:id,first_name,last_name,sur_name,phone_number,guardian_type,guardian_relation,alternative_guardian_phone_number',
            ])
            ->with(['studentGrades' => function ($q) use ($data) {
                $q->where('type', 'years_marks');
                if (!empty($data['term'])) {
                    $q->where('term', $data['term']);
                }
                $q->with('subject:id,name');
            }]);

        if (!empty($data['search'])) {
            $search = $data['search'];
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('sur_name', 'like', "%{$search}%")
                    ->orWhere('registration_number', 'like', "%{$search}%");
            });
        }

        if (!empty($data['institution_id'])) {
            $query->where('institution_id', $data['institution_id']);
        }

        if (!empty($data['classroom_id'])) {
            $query->where('classroom_id', $data['classroom_id']);
        }

        if (!empty($data['region'])) {
            $query->whereHas('institution', fn ($q) => $q->where('region', $data['region']));
        }

        $students = $query->get()->map(function (Student $student) {
            $grades = $student->studentGrades;
            $obtained = round((float) $grades->sum('score'), 2);
            $total = round((float) $grades->sum('total'), 2);
            $percentage = $total > 0 ? round(($obtained / $total) * 100, 2) : 0.0;

            $subjects = $grades
                ->groupBy('subject_id')
                ->map(function ($subjectGrades) {
                    $sample = $subjectGrades->first();
                    $subjectObtained = round((float) $subjectGrades->sum('score'), 2);
                    $subjectTotal = round((float) $subjectGrades->sum('total'), 2);

                    return [
                        'subject_id' => $sample->subject_id,
                        'subject_name' => $sample->subject?->name,
                        'obtained_marks' => $subjectObtained,
                        'total_marks' => $subjectTotal,
                        'percentage' => $subjectTotal > 0 ? round(($subjectObtained / $subjectTotal) * 100, 2) : 0.0,
                    ];
                })
                ->values();

            $guardian = $student->guardian;

            $student->setAttribute('obtained_marks', $obtained);
            $student->setAttribute('total_marks', $total);
            $student->setAttribute('percentage', $percentage);
            $student->setAttribute('subjects', $subjects);
            $student->setAttribute('guardian_contact', $guardian ? [
                'name' => trim(($guardian->first_name ?? '') . ' ' . ($guardian->last_name ?? '') . ' ' . ($guardian->sur_name ?? '')),
                'phone_number' => $guardian->phone_number,
                'relation' => $guardian->guardian_type?->value ?? $guardian->guardian_type,
                'relation_label' => $guardian->guardian_relation,
                'alternate_phone_number' => $guardian->alternative_guardian_phone_number,
            ] : null);

            return $student;
        });

        if (($data['sort'] ?? null) === 'performance_desc') {
            $students = $students->sortByDesc('percentage')->values();
        }

        $perPage = (int) ($data['per_page'] ?? 20);
        $page = (int) (RequestFacade::input('page', 1));
        $slice = $students->slice(($page - 1) * $perPage, $perPage)->values();

        return new LengthAwarePaginator(
            $slice,
            $students->count(),
            $perPage,
            $page,
            ['path' => RequestFacade::url(), 'query' => RequestFacade::query()]
        );
    }
}
