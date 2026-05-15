<?php

namespace App\Actions\StudentPerformance;

use App\Models\StudentPerformance;
use Illuminate\Http\Request;

class ListStudentPerformancesAction
{
    public function handle(Request $request)
    {
        return StudentPerformance::query()->latest()->paginate($request->get('per_page', 10));
    }
}
