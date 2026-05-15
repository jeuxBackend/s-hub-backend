<?php

namespace App\Actions\StudentPerformance;

use App\Models\StudentPerformance;

class UpdateStudentPerformanceAction
{
    public function handle(StudentPerformance $model, array $data)
    {
        $model->update($data);
        return $model;
    }
}
