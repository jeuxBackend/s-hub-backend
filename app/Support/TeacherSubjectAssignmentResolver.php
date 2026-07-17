<?php

namespace App\Support;

use App\Models\ClassSubjectRequirement;
use App\Models\TimetableEntry;
use App\Models\User;
use Illuminate\Support\Collection;

class TeacherSubjectAssignmentResolver
{
    public function teacherCanManage(User $teacher, int $classroomId, int $subjectId): bool
    {
        return TimetableEntry::query()
            ->where('institution_id', $teacher->institution_id)
            ->where('teacher_id', $teacher->id)
            ->where('classroom_id', $classroomId)
            ->where('subject_id', $subjectId)
            ->exists()
            || ClassSubjectRequirement::query()
                ->where('institution_id', $teacher->institution_id)
                ->where('teacher_id', $teacher->id)
                ->where('classroom_id', $classroomId)
                ->where('subject_id', $subjectId)
                ->where('is_active', true)
                ->exists();
    }

    public function getAllowedAssignments(User $teacher, array $filters = []): Collection
    {
        $timetableAssignments = TimetableEntry::query()
            ->where('institution_id', $teacher->institution_id)
            ->where('teacher_id', $teacher->id)
            ->when(isset($filters['config_id']), fn ($query) => $query->where('config_id', $filters['config_id']))
            ->when(isset($filters['academic_year']), fn ($query) => $query->where('academic_year', $filters['academic_year']))
            ->when(isset($filters['term']), fn ($query) => $query->where('term', $filters['term']))
            ->select(['teacher_id', 'classroom_id', 'subject_id'])
            ->with([
                'classroom:id,name,code,institution_id',
                'subject:id,name,code,classroom_id,institution_id',
            ])
            ->groupBy('teacher_id', 'classroom_id', 'subject_id')
            ->orderBy('classroom_id')
            ->orderBy('subject_id')
            ->get();

        $requirementAssignments = ClassSubjectRequirement::query()
            ->where('institution_id', $teacher->institution_id)
            ->where('teacher_id', $teacher->id)
            ->where('is_active', true)
            ->with([
                'classroom:id,name,code,institution_id',
                'subject:id,name,code,classroom_id,institution_id',
            ])
            ->get();

        return $timetableAssignments
            ->concat($requirementAssignments)
            ->unique(fn ($assignment) => $assignment->classroom_id . ':' . $assignment->subject_id)
            ->sortBy([['classroom_id', 'asc'], ['subject_id', 'asc']])
            ->values();
    }
}
