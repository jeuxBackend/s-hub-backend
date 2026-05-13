<?php

namespace App\Http\Controllers\Api\Principal;

use App\Actions\SchoolAdmin\ListSchoolAdminsAction;
use App\Actions\User\ChangeUserRoleAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\ListUserRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Throwable;

class SchoolAdminController extends Controller
{
    public function index(ListUserRequest $request, ListSchoolAdminsAction $action)
    {
        try {
            $requester = auth()->user();

            if (
                !($requester instanceof \App\Models\Admin && $requester->role->value === 'manager') &&
                !($requester instanceof \App\Models\User && $requester->isRole(\App\Enums\UserRole::Principal))
            ) {
                abort(403, 'Unauthorized. Only Principals and Managers can view School Admins.');
            }

            $schoolAdmins = $action->handle($request, $requester);

            $resource = new ResourceCollection(UserResource::collection($schoolAdmins));
            $resource->resource = $schoolAdmins;

            return $this->paginatedResponse($resource);
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function makeSubAdmin(Request $request, ChangeUserRoleAction $action)
    {
        try {
            $request->validate([
                'user_id' => 'required|exists:users,id',
            ]);

            $requester = auth()->user();
            $user = $action->handle($request->user_id, $request->role, $requester);

            return $this->successResponse($user, 'User role updated successfully');

        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }


}
