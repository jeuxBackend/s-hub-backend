<?php

namespace App\Actions\Admin;

use App\Models\Student;
use App\Enums\GenderType;
use App\Enums\TermType;
use Carbon\Carbon;

class CreateGlobalStudentAction
{
    public function handle(array $data)
    {
        $data['gender'] = GenderType::from($data['gender']);
        $data['term'] = TermType::from($data['term']);
        if (!empty($data['dob'])) {
            $data['age'] = Carbon::parse($data['dob'])->age;
        }
        $data['registration_number'] = "student_" . time() . "_" . rand(1000, 9999);
        return Student::create($data);
    }
}
