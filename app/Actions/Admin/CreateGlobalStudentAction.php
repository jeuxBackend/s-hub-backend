<?php

namespace App\Actions\Admin;

use App\Models\Student;
use App\Enums\GenderType;
use App\Enums\TermType;

class CreateGlobalStudentAction
{
    public function handle(array $data)
    {
        $data['gender'] = GenderType::from($data['gender']);
        $data['term'] = TermType::from($data['term']);
        $data['registration_number'] = "student_" . time() . "_" . rand(1000, 9999);
        return Student::create($data);
    }
}
