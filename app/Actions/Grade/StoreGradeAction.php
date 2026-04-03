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
            $students = Student::where('classroom_id', $data['classroom_id'])->get();
            $createdGrades = [];
            // dd($students);
            foreach ($students as $student) {
                $grade = StudentGrade::updateOrCreate(
                    [
                        'student_id'    => $student->id,
                        'classroom_id'  => $data['classroom_id'],
                        'subject_id'    => $data['subject_id'],
                        'term'          => $data['term'],
                    ],
                    [
                        'score'         => $data['score'] ?? null,
                        'total'         => $data['total'] ?? null,
                        'remarks'       => $data['remarks'] ?? null,
                        'date'          => $data['date'] ?? now()->toDateString(),
                        'type'          => $data['type'] ?? null,
                        'recorded_by'   => auth()->id(),
                    ]
                );

                $createdGrades[] = $grade;
            }

            return $createdGrades;
        });
    }
}
