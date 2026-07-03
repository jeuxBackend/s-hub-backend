<?php

namespace App\Http\Controllers\Api\Principal;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Actions\Dashboard\GetPrincipalDashboardStatsAction;
use App\Actions\Dashboard\GetPrincipalAcademicAnalyticsAction;


class PrincipalDashboardController extends Controller
{
    public function stats(GetPrincipalDashboardStatsAction $action)
    {
        $data = $action->handle();

        return $this->successResponse($data, 'Dashboard stats fetched successfully.');
    }

    public function academicAnalytics(Request $request, GetPrincipalAcademicAnalyticsAction $action)
    {
        $data = $action->handle($request->only([
            'classroom_id',
            'limit_subject_toppers',
            'include_class_rankings',
        ]));

        return $this->successResponse($data, 'Academic analytics fetched successfully.');
    }
}
