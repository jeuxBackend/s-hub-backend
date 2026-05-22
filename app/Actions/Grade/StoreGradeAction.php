<?php

namespace App\Actions\Grade;

use App\Models\Student;
use App\Models\StudentGrade;
use Illuminate\Support\Facades\DB;

class StoreGradeAction
{
    public function handle(array $data): array
    {
        // dd($data['date']);
        return DB::transaction(function () use ($data) {
            $grade = StudentGrade::updateOrCreate(
                [
                    'student_id'    => $data['student_id'],
                    'classroom_id'  => $data['classroom_id'],
                    'subject_id'    => $data['subject_id'],
                    'term'          => $data['term'],
                    'type'          => $data['type'] ?? null,
                ],
                [
                    'score'         => $data['score'] ?? null,
                    'total'         => $data['total'] ?? null,
                    'remarks'       => $data['remarks'] ?? null,
                    'date'          => $data['date'] ?? now()->toDateString(),
                    'recorded_by'   => auth()->id(),
                ]
            );

            return [$grade];
        });
    }
}
