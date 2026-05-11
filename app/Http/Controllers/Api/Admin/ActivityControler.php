<?php

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Admin\Dashboard\AdminDashboardAction;
use App\Actions\Admin\GetManagerAction;
use App\Actions\Dashboard\GetManagerSchoolsAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ActivityControler extends Controller
{
    public function dashboard(AdminDashboardAction $action)
    {
        $dashboardData = $action->handle();
        return $this->successResponse($dashboardData, 'Admin dashboard data');
    }

    public function getManagers(GetManagerAction $action)
    {
        $managers = $action->handle();
        return $this->successResponse($managers, 'Admin managers list');
    }

    public function getManagerSchools($id, GetManagerSchoolsAction $action)
    {
        $managers = $action->handle($id);
        return $this->successResponse($managers, 'Admin managers list');
    }
}
