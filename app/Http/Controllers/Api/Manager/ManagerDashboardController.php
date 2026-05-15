<?php

namespace App\Http\Controllers\Api\Manager;

use App\Http\Controllers\Controller;
use App\Actions\Dashboard\GetManagerDashboardStatsAction;
use Illuminate\Http\Request;
use Throwable;

class ManagerDashboardController extends Controller
{
    public function stats(Request $request, GetManagerDashboardStatsAction $action)
    {
        try {
            $data = $action->handle();
            
            return $this->successResponse($data, 'Manager dashboard statistics retrieved successfully.');
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }
}
