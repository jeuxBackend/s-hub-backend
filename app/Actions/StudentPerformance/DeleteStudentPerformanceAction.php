<?php

namespace App\Actions\StudentPerformance;

use App\Models\StudentPerformance;

class DeleteStudentPerformanceAction
{
    public function handle(StudentPerformance $model)
    {
        $model->delete();
        return true;
    }
}
