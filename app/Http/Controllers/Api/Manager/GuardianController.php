<?php

namespace App\Http\Controllers\Api\Manager;

use App\Actions\User\ToggleUserStatusAction;
use App\Actions\Guardian\ListGuardiansAction;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class GuardianController extends Controller
{
    public function index(Request $request, ListGuardiansAction $action)
    {
        // For managers, we might need a custom list action or adjust the existing one
        // to show guardians across all their schools.
        // For now, using the existing one which might need a fix for managers.
        $requester = auth()->user();
        $guardians = $action->handle($request, $requester);
        
        return $this->paginatedResponse($guardians, 'Guardians retrieved successfully');
    }

    public function toggleBlock($id, ToggleUserStatusAction $action)
    {
        $guardian = $action->handle($id);
        return $this->successResponse($guardian, 'Guardian status toggled successfully');
    }
}
