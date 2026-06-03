<?php

namespace App\Actions\Student;

use App\Models\Student;
use App\Models\Subject;
use App\Models\StudentGrade;
use Illuminate\Pagination\LengthAwarePaginator;

class GetAllStudentsYearMarksAction
{
    private const DEFAULT_PER_PAGE = 20;
    private const MAX_PER_PAGE = 100;

    /**
     * Return a paginated list of students with classroom info and
     * yearly subject marks — only subjects belonging to the student's classroom.
     *
     * Filters:
     *  - student_name : partial match on first_name or sur_name (case-insensitive)
     *  - class_name   : partial match on the linked classroom name (case-insensitive)
     *  - per_page     : records per page (default 20, max 100)
     */
    public function handle(array $filters = []): LengthAwarePaginator
    {
        $query = Student::with('classroom');

        // Name filter
        if (!empty($filters['student_name'])) {
            $name = $filters['student_name'];
            $query->where(function ($q) use ($name) {
                $q->where('first_name', 'like', "%{$name}%")
                    ->orWhere('sur_name', 'like', "%{$name}%");
            });
        }

        // Class name filter
        if (!empty($filters['class_name'])) {
            $className = $filters['class_name'];
            $query->whereHas('classroom', function ($q) use ($className) {
                $q->where('name', 'like', "%{$className}%");
            });
        }

        // Resolve per_page
        $perPage = isset($filters['per_page']) && $filters['per_page'] > 0
            ? min((int) $filters['per_page'], self::MAX_PER_PAGE)
            : self::DEFAULT_PER_PAGE;

        // Paginate
        $paginated = $query->paginate($perPage);

        // Collect classroom IDs from the current page
        $classroomIds = $paginated->pluck('classroom_id')->filter()->unique();

        // Load subjects grouped by classroom_id — one query for all classrooms on this page
        $subjectsByClassroom = Subject::whereIn('classroom_id', $classroomIds)
            ->get()
            ->groupBy('classroom_id');

        // Load years_marks grades for students on this page — one query
        $studentIds = $paginated->pluck('id');
        $grades = StudentGrade::where('type', 'years_marks')
            ->whereIn('student_id', $studentIds)
            ->get()
            ->groupBy(function ($grade) {
                return $grade->student_id . '_' . $grade->subject_id;
            });

        // Transform each student record
        $paginated->getCollection()->transform(function ($student) use ($subjectsByClassroom, $grades) {
            $classroomSubjects = $subjectsByClassroom->get($student->classroom_id, collect());

            $subjectsData = [];
            foreach ($classroomSubjects as $subject) {
                $key     = $student->id . '_' . $subject->id;
                $grade   = optional($grades->get($key))->first();
                $obtained = $grade ? (float) $grade->score : 0;
                $max      = $grade ? (float) $grade->total : 0;

                $subjectsData[] = [
                    'subject_id'   => $subject->id,
                    'subject_name' => $subject->name,
                    'obtained'     => $obtained,
                    'total'        => $max,
                    'percentage'   => $max > 0 ? round(($obtained / $max) * 100, 2) : 0,
                ];
            }

            return [
                'student_id'     => $student->id,
                'full_name'      => trim($student->first_name . ' ' . $student->sur_name),
                'reg_number'     => $student->registration_number,
                'classroom_id'   => $student->classroom->id ?? null,
                'classroom_name' => $student->classroom->name ?? null,
                'subjects'       => $subjectsData,
            ];
        });

        return $paginated;
    }
}
