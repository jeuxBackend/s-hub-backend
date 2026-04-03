<?php

namespace App\Http\Controllers\Api\Principal;

use App\Http\Controllers\Controller;
use App\Actions\Dashboard\GetPrincipalDashboardStatsAction;


class PrincipalDashboardController extends Controller
{


    public function stats(GetPrincipalDashboardStatsAction $action)
    {
        $data = $action->handle();

        return $this->successResponse($data, 'Dashboard stats fetched successfully.');
    }
}
