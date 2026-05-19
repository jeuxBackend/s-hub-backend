<?php

namespace App\Actions\Classroom;

use App\Enums\AdminRole;
use App\Models\Classroom;
use App\Models\User;
use App\Models\StudentAttendance;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Collection;
use App\Enums\UserRole;

class ListClassroomsAction
{
    public function handle(User $requester): Collection
    {
        $withRelations = ['inCharge', 'subjects.teacher', 'teachers', 'students.todayAttendance'];

        if ($requester->role == AdminRole::Admin->value) {
            return Classroom::query()
                ->with($withRelations)
                ->latest()
                ->get();
        }

        if ($requester->isRole(UserRole::Principal)) {
            return Classroom::query()
                ->where('institution_id', $requester->institution->id)
                ->with($withRelations)
                ->latest()
                ->get();
        }

        if ($requester->isRole(UserRole::Teacher)) {
            $classrooms = Classroom::query()
                ->whereHas('teachers', fn($q) => $q->where('users.id', $requester->id))
                ->with($withRelations)
                ->latest()
                ->get();

            $this->createMissingAttendanceForToday($requester, $classrooms);
            $classrooms = Classroom::query()
                ->whereHas('teachers', fn($q) => $q->where('users.id', $requester->id))
                ->with($withRelations)
                ->latest()
                ->get();
            return $classrooms;
        }

        if ($requester->isRole(UserRole::SchoolAdmin)) {
            $institutionId = optional($requester->creator)->institution_id;

            return $institutionId
                ? Classroom::query()
                    ->where('institution_id', $institutionId)
                    ->with($withRelations)
                    ->latest()
                    ->get()
                : Classroom::query()->whereRaw('0=1')->get();
        }

        if ($requester->isRole(UserRole::Parent)) {
            return Classroom::query()
                ->whereHas('students', fn($q) => $q->where('guardian_id', $requester->id))
                ->with($withRelations)
                ->latest()
                ->get();
        }

        return Classroom::query()->whereRaw('0=1')->get();
    }

    protected function createMissingAttendanceForToday(User $teacher, $classrooms): void
    {
        $today = Carbon::today()->toDateString();

        foreach ($classrooms as $classroom) {
            $studentIds = $classroom->students->pluck('id');

            if ($studentIds->isEmpty()) {
                continue;
            }

            $alreadyMarked = StudentAttendance::query()
                ->where('classroom_id', $classroom->id)
                ->whereDate('date', $today)
                ->whereIn('student_id', $studentIds)
                ->pluck('student_id')
                ->toArray();

            $missing = $studentIds->diff($alreadyMarked);

            if ($missing->isNotEmpty()) {
                $records = $missing->map(fn($studentId) => [
                    'student_id' => $studentId,
                    'classroom_id' => $classroom->id,
                    'date' => $today,
                    'status' => null,
                    'recorded_by' => $teacher->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                StudentAttendance::insert($records->toArray());
            }
        }
    }
}
