<?php

namespace App\Actions\Classroom;

use App\Models\Classroom;
use App\Models\User;

class CreateClassroomAction
{
    public function handle(array $data, User $creator): Classroom
    {

        return Classroom::create([
            'name'         => $data['name'],
            'code'           => $data['code'] ?? null,
            'created_by'   => $creator->id,
            'institution_id' => $creator->institution->id, // ⚠️ make sure this field exists
        ]);
    }
}
