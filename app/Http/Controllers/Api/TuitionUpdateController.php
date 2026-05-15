<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Actions\TuitionUpdate\ScheduleUpdateAction;
use Illuminate\Http\Request;
use Throwable;

class TuitionUpdateController extends Controller
{
    public function schedule(Request $request, ScheduleUpdateAction $action)
    {
        try {
            $validated = $request->validate([
                'year'         => 'required|string',
                'semester'     => 'required|string',
                'classroom_id' => 'nullable|exists:classrooms,id',
                'is_scheduled' => 'boolean',
                'frequency'    => 'nullable|string|in:once,monthly,after_6_months,yearly'
            ]);

            $requester = auth()->user();
            
            $result = $action->handle($validated, $requester);

            return $this->successResponse(
                is_bool($result) ? null : $result, 
                is_bool($result) ? 'Tuition update sent immediately' : 'Tuition update scheduled successfully'
            );
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }
}
