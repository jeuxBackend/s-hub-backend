<?php

namespace App\Actions\StudentPerformance;

use App\Models\StudentPerformance;

class CreateStudentPerformanceAction
{
    public function handle(array $data)
    {
        return StudentPerformance::create($data);
    }
}
