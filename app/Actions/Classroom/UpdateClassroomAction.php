<?php

namespace App\Actions\Classroom;

use App\Models\Classroom;

class UpdateClassroomAction
{
    public function handle(Classroom $classroom, array $data): Classroom
    {
        $classroom->update([
            'name' => $data['name'] ?? $classroom->name,
            'code' => $data['code'] ?? $classroom->code,
        ]);

        return $classroom->refresh();
    }
}
