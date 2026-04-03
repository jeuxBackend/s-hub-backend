<?php

namespace App\Actions\Subject;

use App\Models\Subject;

class StoreSubjectAction
{
    public function handle(array $data): Subject
    {


        return Subject::create([
            'name'         => $data['name'],
            'code'           => $data['code'] ?? null,
            'classroom_id'   => $data['classroom_id'],
            'institution_id' =>  auth()->user()->institution->id, // ⚠️ make sure this field exists
        ]);
    }
}
