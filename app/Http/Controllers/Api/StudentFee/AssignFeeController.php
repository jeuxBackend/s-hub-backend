<?php

namespace App\Http\Controllers\Api\StudentFee;

use App\Http\Controllers\Controller;
use App\Http\Requests\StudentFee\AssignFeeRequest;
use App\Actions\StudentFee\AssignFeeAction;

class AssignFeeController extends Controller
{
    public function __invoke(AssignFeeRequest $request, AssignFeeAction $assignFeeAction)
    {
        $assignFeeAction->handle($request->validated());

        return $this->successResponse(null, 'Student fee assigned successfully');
    }
}
