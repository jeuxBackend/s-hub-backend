<?php

namespace App\Http\Controllers\Api\Manager;

use App\Actions\User\ToggleUserStatusAction;
use App\Actions\Guardian\ListGuardiansAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GuardianController extends Controller
{
    public function index(Request $request, ListGuardiansAction $action)
    {
        $guardians = $action->handle($request);

        return $this->paginatedResponse(
            JsonResource::collection($guardians),
            'Guardians retrieved successfully'
        );
    }

    public function toggleBlock($id, ToggleUserStatusAction $action)
    {
        $guardian = $action->handle($id);
        return $this->successResponse($guardian, 'Guardian status toggled successfully');
    }
}
