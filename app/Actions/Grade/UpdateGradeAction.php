<?php

namespace App\Actions\Grade;

use App\Models\StudentGrade;
use Illuminate\Support\Facades\DB;

class UpdateGradeAction
{
   public function handle(StudentGrade $grade, array $data): StudentGrade
    {
        $grade->update($data);

        return $grade;
    }
}
