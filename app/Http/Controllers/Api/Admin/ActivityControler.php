<?php

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Admin\Dashboard\AdminDashboardAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ActivityControler extends Controller
{
    public function dashboard(AdminDashboardAction $action)
    {
        $dashboardData = $action->handle(auth()->user()->assignedInstitutionIds());
        return $this->successResponse($dashboardData, 'Admin dashboard data');
    }
}
