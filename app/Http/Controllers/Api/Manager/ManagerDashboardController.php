<?php

namespace App\Http\Controllers\Api\Manager;

use App\Http\Controllers\Controller;
use App\Actions\Dashboard\GetDistinctRegionsAction;
use App\Actions\Dashboard\GetManagerDashboardStatsAction;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

class ManagerDashboardController extends Controller
{
    public function stats(Request $request, GetManagerDashboardStatsAction $action)
    {
        try {
            $request->validate([
                'region' => 'nullable|string|max:255',
                'fees_period' => ['nullable', Rule::in(['7D', '1M', '1Y'])],
                'engagement_period' => ['nullable', Rule::in(['7D', '1M', '1Y'])],
            ]);

            $data = $action->handle(
                $request->input('region'),
                $request->input('fees_period', '7D'),
                $request->input('engagement_period', '7D')
            );

            return $this->successResponse($data, 'Manager dashboard statistics retrieved successfully.');
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function regions(GetDistinctRegionsAction $action)
    {
        try {
            return $this->successResponse($action->handle(), 'Regions retrieved successfully.');
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }
}
