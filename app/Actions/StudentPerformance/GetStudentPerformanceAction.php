<?php

namespace App\Actions\StudentPerformance;

use App\Models\StudentPerformance;

class GetStudentPerformanceAction
{
    public function handle($id)
    {
        return StudentPerformance::findOrFail($id);
    }
}
