<?php

namespace App\Actions\Classroom;

use App\Models\Classroom;
use App\Models\User;

use Illuminate\Support\Facades\DB;

class CreateClassroomAction
{
    public function handle(array $data, User $creator): Classroom
    {
        return DB::transaction(function () use ($data, $creator) {
            $classroom = Classroom::create([
                'name'           => $data['name'],
                'code'           => $data['code'] ?? null,
                'in_charge_id'   => $data['in_charge_id'] ?? null,
                'institution_id' => $creator->institution->id,
            ]);

            if (!empty($data['subjects'])) {
                foreach ($data['subjects'] as $subjectData) {
                    $classroom->subjects()->create([
                        'name'           => $subjectData['name'],
                        'institution_id' => $creator->institution->id,
                        'teacher_id'     => $subjectData['teacher_id'] ?? null,
                        'start_time'     => $subjectData['start_time'] ?? null,
                        'end_time'       => $subjectData['end_time'] ?? null,
                    ]);
                }
            }

            return $classroom;
        });
    }
}
