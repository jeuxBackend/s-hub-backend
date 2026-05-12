<?php

namespace App\Actions\Admin;

use App\Models\Student;
use App\Enums\GenderType;
use App\Enums\TermType;

class UpdateGlobalStudentAction
{
    public function handle(array $data, $id)
    {
        $student = Student::findOrFail($id);
        if (isset($data['gender'])) {
            $data['gender'] = GenderType::from($data['gender']);
        }
        if (isset($data['term'])) {
            $data['term'] = TermType::from($data['term']);
        }
        $student->update($data);
        return $student;
    }
}
